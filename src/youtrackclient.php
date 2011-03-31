<?php
namespace YouTrack;
require("connection.php");
/**
 * This file holds all youtrack related classes regarding data types.
 *
 * @author Jens Jahnke <jan0sch@gmx.net>
 * Created at: 29.03.11 16:29
 */

/**
 * A class extending the standard php exception.
 */
class YouTrackException extends \Exception {
  /**
   * Constructor
   *
   * @param string $url The url that triggered the error.
   * @param array $response The output of <code>curl_getinfo($resource)</code>.
   * @param array $content The content returned from the url.
   */
  public function __construct($url, $response, $content) {
    $code = 0;
    $previous = NULL;
    $message = "Error for '" . $url . "': " . $response['http_code'];
    if (!empty($response['content_type']) && !preg_match('/text\/html/', $response['content_type'])) {
      $xml = simplexml_load_string($content);
      $error = new YouTrackError($xml);
      $message .= ": " . $error->__get("error");
    }
    parent::__construct($message, $code, $previous);
  }
}

/**
 * A class describing a youtrack object.
 */
class YouTrackObject {
  protected $youtrack = NULL;
  protected $attributes = array();

  public function __construct(\SimpleXMLElement $xml = NULL, Connection $youtrack = NULL) {
    $this->youtrack = $youtrack;
    if (!empty($xml)) {
      if (!($xml instanceof \SimpleXMLElement)) {
        throw new \Exception("An instance of SimpleXMLElement expected!");
      }
      $this->_update_attributes($xml);
      $this->_update_children_attributes($xml);
    }
  }

  public function __get($name) {
    if (!empty($this->attributes["$name"])) {
      return $this->attributes["$name"];
    }
    throw new \Exception("No such property: $name");
  }

  public function __set($name, $value) {
    $this->attributes["$name"] = $value;
  }

  protected function _update_attributes(\SimpleXMLElement $xml) {
    foreach ($xml->xpath('/*') as $node) {
      foreach ($node->attributes() as $key => $value) {
        $this->attributes["$key"] = $value;
      }
    }
  }

  protected function _update_children_attributes(\SimpleXMLElement $xml) {
    foreach ($xml->xpath('//*') as $node) {
      foreach ($node->attributes() as $key => $value) {
        $this->__set($key, $value);
      }
    }
  }
}

/**
 * A class describing a youtrack error.
 */
class YouTrackError extends YouTrackObject {
  public function __construct(\SimpleXMLElement $xml = NULL, Connection $youtrack = NULL) {
    parent::__construct($xml, $youtrack);
  }

  protected function _update_attributes(\SimpleXMLElement $xml) {
    foreach ($xml->xpath('/error') as $node) {
      $this->attributes['error'] = (string) $node;
    }
  }
}

/**
 * A class describing a youtrack issue.
 */
class Issue extends YouTrackObject {
  private $links = array();
  private $attachments = array();

  public function __construct(\SimpleXMLElement $xml = NULL, Connection $youtrack = NULL) {
    parent::__construct($xml, $youtrack);
    if ($xml) {
      $links = $xml->xpath('//links');
      if (count($links) > 0) {
        foreach ($links as $link) {
          foreach ($link->xpath('//issueLink') as $node) {
            $this->links[] = new Issue($node, $youtrack);
          }
        }
      } else {
        $this->links = array();
      }

      $attachments = $xml->xpath('//attachments');
      if (count($attachments) > 0) {
        foreach ($attachments as $att) {
          foreach ($att->xpath('//fileUrl') as $node) {
            $this->attachments[] = new Attachment($node, $youtrack);
          }
        }
      } else {
        $this->attachments = array();
      }
    }
  }
}

/**
 * A class describing a youtrack comment.
 */
class Comment extends YouTrackObject {

}

/**
 * A class describing a youtrack link.
 */
class Link extends YouTrackObject {

}

/**
 * A class describing a youtrack attachment.
 */
class Attachment extends YouTrackObject {

}

/**
 * A class describing a youtrack user.
 */
class User extends YouTrackObject {

}

/**
 * A class describing a youtrack group.
 */
class Group extends YouTrackObject {

}

/**
 * A class describing a youtrack role.
 */
class Role extends YouTrackObject {

}

/**
 * A class describing a youtrack project.
 */
class Project extends YouTrackObject {

}

/**
 * A class describing a youtrack subsystem.
 */
class Subsystem extends YouTrackObject {

}

/**
 * A class describing a youtrack version.
 */
class Version extends YouTrackObject {

}

/**
 * A class describing a youtrack build.
 */
class Build extends YouTrackObject {

}

/**
 * A class describing a youtrack issue link type.
 */
class IssueLinkType extends YouTrackObject {

}

/**
 * A class describing a youtrack custom field.
 */
class CustomField extends YouTrackObject {

}

/**
 * A class describing a youtrack project custom field.
 */
class ProjectCustomField extends YouTrackObject {

}

/**
 * An enum bundle.
 */
class EnumBundle extends YouTrackObject {

}
