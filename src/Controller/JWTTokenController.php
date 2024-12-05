<?php 

namespace Drupal\private_media_controller\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

class JWTTokenController extends ControllerBase {
  public function getToken() {

    $jwtService = \Drupal::service('jwt.authentication.jwt');
    $token = $jwtService->generateToken();
    $data = [
        'jwt-token' => $token
    ];
    return new JsonResponse($data);
  }

  function serveMedia(MediaInterface $media) { 
    if ($media->hasField('field_media_document') && !$media->get('field_media_document')->isEmpty()) {
       $file = $media->get('field_media_document')->entity;
       if ($file) {
        $file_uri = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
       }
    }
    /*print($file_uri);
    $data = [
       'file' => $file_uri,
       'token' => $_GET['token']
    ];
    return new JsonResponse($data);*/
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
}
