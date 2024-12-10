
<?php 

namespace Drupal\private_media_controller\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\views\Views;
use Drupal\media\Entity\Media;
use Drupal\webform\Entity\WebformSubmission;

class JWTTokenController extends ControllerBase {
  public function getToken() {
    $config = \Drupal::configFactory()->getEditable('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();
    
    $jwtService = \Drupal::service('jwt.authentication.jwt');
    $token = $jwtService->generateToken();

    $data = [
        'jwt-token' => $token
    ];
    return new JsonResponse($data);
  }

  public function serveMedia(MediaInterface $media, $token) { 
    
    if ($media->hasField('field_media_document') && !$media->get('field_media_document')->isEmpty()) {
       $file = $media->get('field_media_document')->entity;
       if ($file) {
        $file_uri = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
       }
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $file_uri,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '. $token
      ),
    ));

    $response = curl_exec($curl);
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
                        // Get the referenced taxonomy term
                        $term = $media->get('field_media_use')->entity;

                        if ($term instanceof Term) {
                            // Check if the term name is "Temporary Downloadable"
                            if ($term->getName() === 'Temporary Downloadable') {
                                return $media;
                            } else {
                                return false;
                            }
                        } else {
                            return false;
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

  public function accessGrant(String $token, String $nodeinfo, $submitted) {
    $parts = explode(", ", $nodeinfo);
    $nid = $parts[count($parts) - 1];
    
    // Load the webform submission by token
    $submission = \Drupal::entityTypeManager()
        ->getStorage('webform_submission')
        ->loadByProperties(['token' => $token]);

    // Since loadByProperties returns an array, get the first element
    $submission = reset($submission);
    drupal_log($submission->id());

    // validate the link is still within 1 day 
    $end_time = $submitted + (24 * 60 * 60);

    $current_time = time();
    if ($submission && ($current_time >= $submitted && $current_time <= $end_time)) {

      $config = \Drupal::configFactory()->getEditable('system.performance');
      $config->set('cache.page.max_age', 300);
      $config->save();
      
      $jwtService = \Drupal::service('jwt.authentication.jwt');
      $jwt_token = $jwtService->generateToken();

      $media = $this->getMedias($nid);

      if ($media) {
        $this->serveMedia($media, $jwt_token);
      }
      else {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access forbidden';
        exit;
      }
    }   
  }
}
