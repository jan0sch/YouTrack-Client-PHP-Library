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
  private $debug_verbose = FALSE; // Set to TRUE to enable verbose logging of curl messages.

  public function __construct($url, $login, $password) {
    $this->http = curl_init();
    $this->url = $url;
    $this->base_url = $url . '/rest';
    $this->_login($login, $password);
  }

  private function _login($login, $password) {
    curl_setopt($this->http, CURLOPT_POST, TRUE);
    curl_setopt($this->http, CURLOPT_HTTPHEADER, array('Content-Length' => 0)); //FIXME This doesn't work if youtrack is running behind lighttpd! @see http://redmine.lighttpd.net/issues/1717
    curl_setopt($this->http, CURLOPT_URL, $this->base_url . '/user/login?login=' . $login . '&password=' . $password);
    curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
    $content = curl_exec($this->http);
    $response = curl_getinfo($this->http);
    if ((int) $response['http_code'] != 200) {
      throw new YouTrackException('/user/login', $response, $content);
    }
    $this->headers[CURLOPT_COOKIE] = $content;
    $this->headers[CURLOPT_HTTPHEADER] = array('Cache-Control' => 'no-cache');
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
  private function _request($method, $url, $body = NULL, $ignore_status = 0) {
    $headers = $this->headers;
    if ($method == 'PUT' || $method == 'POST') {
      $headers[CURLOPT_HTTPHEADER]['Content-Type'] = 'application/xml; charset=UTF-8';
      $headers[CURLOPT_HTTPHEADER]['Content-Length'] = mb_strlen($body);
    }
    switch ($method) {
      case 'GET':
        curl_setopt($this->http, CURLOPT_HTTPGET, TRUE);
        break;
      case 'PUT':
        $size = filesize($body);
        if (!$size) {
          throw new \Exception("Can't open file $body!");
        }
        $handle = fopen($body, 'r');
        curl_setopt($this->http, CURLOPT_PUT, TRUE);
        curl_setopt($this->http, CURLOPT_INFILE, $handle);
        curl_setopt($this->http, CURLOPT_INFILESIZE, 0);
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
    curl_setopt($this->http, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->http, CURLOPT_VERBOSE, $this->debug_verbose);
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

  private function _request_xml($method, $url, $body = NULL, $ignore_status = 0) {
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

  private function _get($url) {
    return $this->_request_xml('GET', $url);
  }

  private function _put($url) {
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
    $file = file_get_contents($url);
    if ($file === FALSE) {
      throw new \Exception("An error occured while trying to retrieve the following file: $url");
    }
    return $file;
  }
}
