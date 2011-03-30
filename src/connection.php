<?php
namespace YouTrack;
/**
 * A class for connecting to a youtrack instance.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * Created at: 29.03.11 16:13
 */
class Connection
{
    private $http = NULL;
    private $url = '';
    private $base_url = '';
    private $headers = array();

    public function __construct(\string $url, \string $login, \string $password)
    {
        $this->http = curl_init();
        $this->url = $url;
        $this->base_url = $url . '/rest';
        $this->_login($login, $password);
    }

    private function _login(\string $login, \string $password)
    {
        curl_setopt($this->http, CURLOPT_POST, TRUE);
        curl_setopt($this->http, CURLOPT_HTTPHEADER, array('Content-Length' => '0'));
        curl_setopt($this->http, CURLOPT_URL, $this->base_url . '/user/login?login=' . $login . '&password=' . $password);
        curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
        $content = curl_exec($this->http);
        $response = curl_getinfo($this->http);
        if ((int)$response['http_code'] != 200) {
            throw new YouTrackException('/user/login', $response, $content);
        }
        $this->headers[CURLOPT_COOKIE] = $content;
        $this->headers[CURLOPT_HTTPHEADER] = array('Cache-Control' => 'no-cache');
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
    private function _request(\string $method, \string $url, \string $body = NULL, \int $ignore_status = 0)
    {
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
                break;
            default:
                throw new \Exception("Unknown method $method!");
        }
        curl_setopt($this->http, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->http, CURLOPT_RETURNTRANSFER, TRUE);
        $content = curl_exec($this->http);
        $response = curl_getinfo($this->http);
        if ((int)$response['http_code'] != 200 && (int)$response['http_code'] != 201 && (int)$response['http_code'] != $ignore_status) {
            throw new YouTrackException($url, $response, $content);
        }

        return array(
            'content' => $content,
            'response' => $response,
        );
    }

    private function _request_xml(\string $method, \string $url, \string $body = NULL, \int $ignore_status = 0)
    {
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

    private function _get(\string $url)
    {
        return $this->_request_xml('GET', $url);
    }

    private function _put(\string $url)
    {
        return $this->_request_xml('PUT', $url, '<empty/>\n\n');
    }

    public function get_issue(\string $id)
    {
        $issue = $this->_get('/issue/'. $id);
        //FIXME Add code to generate the issue out of the xml.
    }
}
