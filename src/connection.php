<?php
namespace YouTrack;
/**
 * A class for connecting to a youtrack instance.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * Created at: 29.03.11 16:13
 */
class Connection {
  private $http = NULL;
  private $url = '';
  private $base_url = '';
  private $headers = array();
  private $cookies = array();
  private $debug_verbose = FALSE; // Set to TRUE to enable verbose logging of curl messages.
  private $user_agent = 'Mozilla/5.0'; // Use this as user agent string.

  public function __construct($url, $login, $password) {
    $this->http = curl_init();
    $this->url = $url;
    $this->base_url = $url . '/rest';
    $this->_login($login, $password);
  }

  protected function _login($login, $password) {
    curl_setopt($this->http, CURLOPT_POST, TRUE);
    curl_setopt($this->http, CURLOPT_HTTPHEADER, array('Content-Length: 0')); //FIXME This doesn't work if youtrack is running behind lighttpd! @see http://redmine.lighttpd.net/issues/1717
    curl_setopt($this->http, CURLOPT_URL, $this->base_url . '/user/login?login='. urlencode($login) .'&password='. urlencode($password));
    curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->http, CURLOPT_HEADER, TRUE);
    curl_setopt($this->http, CURLOPT_USERAGENT, $this->user_agent);
    curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
    $content = curl_exec($this->http);
    $response = curl_getinfo($this->http);
    if ((int) $response['http_code'] != 200) {
      throw new YouTrackException('/user/login', $response, $content);
    }
    $cookies = array();
    preg_match_all('/^Set-Cookie: (.*?)=(.*?)$/sm', $content, $cookies, PREG_SET_ORDER);
    foreach($cookies as $cookie) {
      $parts = parse_url($cookie[0]);
      $this->cookies[] = $parts['path'];
    }
    $this->headers[CURLOPT_HTTPHEADER] = array('Cache-Control: no-cache');
    curl_close($this->http);
  }

  /**
   * Execute a request with the given parameters and return the response.
   *
   * @throws \Exception|YouTrackException An exception is thrown if an error occurs.
   * @param string $method The http method (GET, PUT, POST).
   * @param string $url The request url.
   * @param string $body Data that should be send or the filename of the file if PUT is used.
   * @param int $ignore_status Ignore the given http status code.
   * @return array An array holding the response content in 'content' and the response status
   * in 'response'.
   */
  protected function _request($method, $url, $body = NULL, $ignore_status = 0) {
    $this->http = curl_init($this->base_url . $url);
    $headers = $this->headers;
    if ($method == 'PUT' || $method == 'POST') {
      $headers[CURLOPT_HTTPHEADER][] = 'Content-Type: application/xml; charset=UTF-8';
      $headers[CURLOPT_HTTPHEADER][] = 'Content-Length: '. mb_strlen($body);
    }
    switch ($method) {
      case 'GET':
        curl_setopt($this->http, CURLOPT_HTTPGET, TRUE);
        break;
      case 'PUT':
        $handle = NULL;
        $size = 0;
        // Check if we got a file or just a string of data.
        if (file_exists($body)) {
          $size = filesize($body);
          if (!$size) {
            throw new \Exception("Can't open file $body!");
          }
          $handle = fopen($body, 'r');
        }
        else {
          $size = mb_strlen($body);
          $handle = fopen('data://text/plain,' . $body,'r');
        }
        curl_setopt($this->http, CURLOPT_PUT, TRUE);
        curl_setopt($this->http, CURLOPT_INFILE, $handle);
        curl_setopt($this->http, CURLOPT_INFILESIZE, $size);
        break;
      case 'POST':
        curl_setopt($this->http, CURLOPT_POST, TRUE);
        if (!empty($body)) {
          curl_setopt($this->http, CURLOPT_POSTFIELDS, $body);
        }
        break;
      default:
        throw new \Exception("Unknown method $method!");
    }
    curl_setopt($this->http, CURLOPT_HTTPHEADER, $headers[CURLOPT_HTTPHEADER]);
    curl_setopt($this->http, CURLOPT_USERAGENT, $this->user_agent);
    curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
    curl_setopt($this->http, CURLOPT_COOKIE, implode(';', $this->cookies));
    $content = curl_exec($this->http);
    $response = curl_getinfo($this->http);
    curl_close($this->http);
    if ((int) $response['http_code'] != 200 && (int) $response['http_code'] != 201 && (int) $response['http_code'] != $ignore_status) {
      throw new YouTrackException($url, $response, $content);
    }

    return array(
      'content' => $content,
      'response' => $response,
    );
  }

  protected function _request_xml($method, $url, $body = NULL, $ignore_status = 0) {
    $r = $this->_request($method, $url, $body, $ignore_status);
    $response = $r['response'];
    $content = $r['content'];
    if (!empty($response['content_type'])) {
      if (preg_match('/application\/xml/', $response['content_type']) || preg_match('/text\/xml/', $response['content_type'])) {
        return simplexml_load_string($content);
      }
    }
    return $content;
  }

  protected function _get($url) {
    return $this->_request_xml('GET', $url);
  }

  protected function _put($url) {
    return $this->_request_xml('PUT', $url, '<empty/>\n\n');
  }

  public function get_issue($id) {
    $issue = $this->_get('/issue/' . $id);
    return new Issue($issue);
  }

  public function create_issue($project, $assignee, $summary, $description, $priority, $type, $subsystem, $state, $affectsVersion, $fixedVersion, $fixedInBuild) {
    $parameters = '';
    $args = array(
      'project' => $project,
      'assignee' => $assignee,
      'summary' => $summary,
      'description' => $description,
      'priority' => $priority,
      'type' => $type,
      'subsystem' => $subsystem,
      'state' => $state,
      'affectsVersion' => $affectsVersion,
      'fixedVersion' => $fixedVersion,
      'fixedInBuild' => $fixedInBuild,
    );
    foreach ($args as $key => $value) {
      $parameters .= $key . '=' . urlencode($value) . '&';
    }
    rtrim($parameters, '&');
    $issue = $this->_request_xml('POST', '/issue?' . $parameters);
    return new Issue($issue);
  }

  public function get_comments($id) {
    $comments = array();
    $req = $this->_request('GET', '/issue/'. $id .'/comment');
    $xml = simplexml_load_string($req['content']);
    foreach($xml->children() as $node) {
      $comments[] = new Comment($node);
    }
    return $comments;
  }

  public function get_attachments($id) {
    $attachments = array();
    $req = $this->_request('GET', '/issue/'. $id .'/attachment');
    $xml = simplexml_load_string($req['content']);
    foreach($xml->children() as $node) {
      $attachments[] = new Comment($node);
    }
    return $attachments;
  }

  public function get_attachment_content($url) {
    //TODO Switch to curl for better error handling.
    $file = file_get_contents($url);
    if ($file === FALSE) {
      throw new \Exception("An error occured while trying to retrieve the following file: $url");
    }
    return $file;
  }

  public function create_attachment_from_attachment($issue_id, Attachment $attachment) {
    throw new \Exception("No yet implemented!");
  }

  public function create_attachment($issue_id, $name, $content, $author_login = '', $content_type = NULL, $content_length = NULL, $created = NULL, $group = '') {
    throw new \Exception("No yet implemented!");
  }

  public function get_links($id , $outward_only = FALSE) {
    $links = array();
    $req = $this->_request('GET', '/issue/'. urlencode($id) .'/link');
    $xml = simplexml_load_string($req['content']);
    foreach($xml->children() as $node) {
      if (($node->attributes()->source != $id) || !$outward_only) {
        $links[] = new Link($node);
      }
    }
    return $links;
  }

  public function get_user($login) {
    return new User($this->_get('/admin/user/'. urlencode($login)));
  }

  public function create_user($user) {
    $this->import_users(array($user));
  }

  public function create_user_detailed($login, $full_name, $email, $jabber) {
    $this->import_users(array(array('login' => $login, 'fullName' => $full_name, 'email' => $email, 'jabber' => $jabber)));
  }

  public function import_users($users) {
    if (count($users) <= 0) {
      return;
    }
    $xml = "<list>\n";
    foreach ($users as $user) {
      $xml .= "  <user";
      foreach ($user as $key => $value) {
        $xml .= " $key=". urlencode($value);
      }
      $xml .= " />\n";
    }
    $xml .= "</list>";
    return $this->_request_xml('PUT', '/import/users', $xml, 400);
  }

  public function get_project($project_id) {
    $project = $this->_get('/admin/project/'. urlencode($project_id));
    return new Project($project);
  }

  public function get_project_assignee_groups($project_id) {
    $xml = $this->_get('/admin/project/'. urlencode($project_id) .'/assignee/group');
    $groups = array();
    foreach ($xml->children() as $group) {
      $groups[] = new Group(new \SimpleXMLElement($group->asXML()));
    }
    return $groups;
  }

  public function get_group($name) {
    return new Group($this->_get('/admin/group/'. urlencode($name)));
  }

  public function get_user_groups($login) {
    $xml = $this->_get('/admin/user/'. urlencode($login) .'/group');
    $groups = array();
    foreach ($xml->children() as $group) {
      $groups[] = new Group(new \SimpleXMLElement($group->asXML()));
    }
    return $groups;
  }
}
