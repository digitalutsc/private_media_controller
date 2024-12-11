<?php 

namespace Drupal\private_media_controller\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\media\MediaInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\views\Views;
use Drupal\media\Entity\Media;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;
use Drupal\jwt\JsonWebToken\JsonWebToken;

class JWTTokenController extends ControllerBase {

  /**
   * Login with service account
   */
  function programmatically_login_user($user_id) {
    // Load the user entity.
    $user = User::load($user_id);

    if ($user) {
      // Get the current request.
      $request = \Drupal::requestStack()->getCurrentRequest();

      // Log in the user.
      user_login_finalize($user);

      // Set the session for the user.
      \Drupal::service('session_manager')->start();
      \Drupal::service('current_user')->setAccount($user);

      // Set the user in the request.
      $request->attributes->set('user', $user);

      return $user;
    } else {
    }
  }

  /**
   * Logout user
   */

  function programmatically_logout_user() {
    // Get the current request.
    $request = \Drupal::requestStack()->getCurrentRequest();

    // Log out the user.
    user_logout();

    // Invalidate the session.
    \Drupal::service('session_manager')->destroy();

    // Clear the current user.
    \Drupal::service('current_user')->setAccount(new \Drupal\Core\Session\AnonymousUserSession());

    // Clear the user from the request.
    $request->attributes->remove('user');

  }

    /**
     * Generate JWT token with expiration
     */
  public function getJWTToken() {
    $account = $this->programmatically_login_user(1);

    $jwt = new JsonWebToken();
    $now = time();
    $jwt->setClaim('iat', time());
    $jwt->setClaim('exp', $now + 120);
    $jwt->setClaim(['drupal', 'uuid'], $account->uuid());  
    
    /** @var \Drupal\Core\Authentication\AuthenticationProviderInterface $jwtService */
    $jwtService = \Drupal::service('jwt.authentication.jwt');
    
    /** @var \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder */
    $transcoder = \Drupal::service('jwt.transcoder');
    
    $token = $transcoder->encode($jwt);
    $this->programmatically_logout_user();
    return $token;

    /*$data = [
      'jwt-token' => $token
    ];
    return new JsonResponse($data);*/
    
  }

  public function serveMedia(MediaInterface $media) { 
    $jwt_token = $this->getJWTToken();

    if ($media->hasField('field_media_document') && !$media->get('field_media_document')->isEmpty()) {
       $file = $media->get('field_media_document')->entity;
       if ($file) {
        $file_uri = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
       }
    }
    print($file_uri);
    $curl = curl_init();
    $options = array(
      CURLOPT_URL => $file_uri,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '. $jwt_token
      ),
    );
    drupal_log(json_encode($options));
    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    drupal_log(json_encode($response));
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($http_code === 200) {
        $filename = basename($file_uri);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . strlen($response));
        header('Connection: close');
        echo $response;
        exit;
    }
    else if ($http_code === 403) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access forbidden';
        exit;
    }
    else {
        // Set the response to 400 Bad Request
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid request';
        exit;
    }
  }

  /**
   * Query the media based on Node
   */
  public function getMedias($nid) {
    // Load the node
    $node = Node::load($nid);

    if ($node) {
        // Check if the node has the field_islandora_object_media field
        if ($node->hasField('field_islandora_object_media') && !$node->get('field_islandora_object_media')->isEmpty()) {
            // Get the media entity references
            $media_references = $node->get('field_islandora_object_media')->referencedEntities();

            // Iterate through the media references
            foreach ($media_references as $media) {
                if ($media instanceof Media) {
                    $media_name = $media->getName();
                    // Check if the media entity has the field_media_use field
                    if ($media->hasField('field_media_use') && !$media->get('field_media_use')->isEmpty()) {
                        // Get the referenced taxonomy terms
                        $terms = $media->get('field_media_use')->referencedEntities();

                        // Iterate through the referenced terms
                        foreach ($terms as $term) {
                            // Check if the term name is "Temporary Downloadable"
                            if ($term->getName() === 'Temporary Downloadable') {
                                return $media;
                            }
                        }
                    } else {
                        return false; 
                    }
                }
            }
        } else {
            return false;
        }
    } else {
        return false;
    }

  }

  /**
   * Check access control and grand temporary access
   */
  public function accessGrant(String $submission_token, String $nodeinfo, $submitted) {
    $parts = explode(", ", $nodeinfo);
    $nid = $parts[count($parts) - 1];
    
    // Load the webform submission by token
    $submission = \Drupal::entityTypeManager()
        ->getStorage('webform_submission')
        ->loadByProperties(['token' => $submission_token]);

    // Since loadByProperties returns an array, get the first element
    $submission = reset($submission);

    // validate the link is still within 1 day 
    $end_time = $submitted + (24 * 60 * 60);

    $current_time = time();
    if ($submission && ($current_time >= $submitted && $current_time <= $end_time)) {
      $media = $this->getMedias($nid);

      if ($media) {
        $this->serveMedia($media);
      }
      else {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access forbidden';
        exit;
      }
    }   
  }
}
