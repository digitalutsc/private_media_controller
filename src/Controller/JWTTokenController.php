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
        'Authorization: Bearer '. $_GET['token']
      ),
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    print ($http_code);
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

  public function accessGrant(String $token) {
    // suppose to be name+email+nid+expired_time
    $token = base64_decode($token);
    $parts = split("+", $token);
    $name = $parts[0];
    $email = $parts[1];
    $nid = $parts[2];
    $expired = $parts[3];

    $condtion = true; 
    if ($condition) { 
      $config = \Drupal::configFactory()->getEditable('system.performance');
      $config->set('cache.page.max_age', 300);
      $config->save();
      
      $jwtService = \Drupal::service('jwt.authentication.jwt');
      $access_token = $jwtService->generateToken();

      // Load the view by its machine name
      $view = Views::getView('media_to_request_access');

      if ($view) {
          // Set the display ID (e.g., 'default' or any other display ID)
          $view->setDisplay('default');

          // Set the contextual filters
          // Assuming you have one contextual filter, replace 'contextual_value' with your actual value
          $view->setArguments($nid);

          // Execute the view
          $view->execute();

          // Get the rendered output
          $rendered_output = $view->render();

          // If you need the result set
          $mid = $view->result;

          // Load the media entity
          $media = Media::load($mid);

          $this->serveMedia($media, $access_token);
      } else {
          // Handle the case where the view is not found
      }
    }   
  }
}
