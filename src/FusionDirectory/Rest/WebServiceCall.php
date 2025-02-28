<?php

namespace FusionDirectory\Rest;
class WebServiceCall
{
  private $URL, $method, $token; //String
  private $data, $authData; //Array
  /**
   * @var false|resource
   */
  private $ch;

  /**
   * @param string $URL
   * @param string $method
   * @param array $data
   * @param array $authData
   */
  public function __construct (string $URL, string $method, array $data = [], array $authData = [])
  {
    $this->URL      = $URL;
    $this->data     = $data;
    $this->method   = $method;
    $this->authData = $authData;
  }

  /**
   * @param string|NULL $URL
   * @param array|NULL $data
   * @param string|NULL $method
   */
  public function setCurlSettings (string $URL = NULL, array $data = NULL, string $method = NULL)
  {
    $this->ch = !empty($URL) ? curl_init($URL) : curl_init($this->URL);

    // We require to set a proper http_agent
    $os              = PHP_OS;
    $phpVersion      = PHP_VERSION;
    $customUserAgent = "FusionDirectory/Integrator ($os; PHP $phpVersion; https://www.fusiondirectory.org) WebserviceLib";

    // Manage a trick to perform refresh on user DN
    if (!empty($data) && array_key_exists('refreshUser', $data)) {
      // Empty array is required to update the user dn without passing information, json_encode will transform it to {}
      $this->data = [];
    } else if (!empty($data)) {
      $this->data = $data;
    }

    if (!empty($method)) {
      $this->method = $method;

      // set the curl options based on the method required.
      switch (strtolower($this->method)) {
        case 'patch' :
          curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
          curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($this->data));
          curl_setopt($this->ch, CURLOPT_USERAGENT, $customUserAgent);
          break;
        case 'get' :
          // No curl_setopt required
          break;
        case 'put':
          curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');
          curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($this->data));
          curl_setopt($this->ch, CURLOPT_USERAGENT, $customUserAgent);
          break;
        case 'delete':
          curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
          curl_setopt($this->ch, CURLOPT_USERAGENT, $customUserAgent);
          break;
        case 'post':
          curl_setopt($this->ch, CURLOPT_POST, TRUE);
          curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($this->data));
          curl_setopt($this->ch, CURLOPT_USERAGENT, $customUserAgent);
          break;
      }
    }

    // Handle token retrieval via basic user/pass authentication.

    if (empty($this->token)) {
      if (empty($this->authData)) {
        $this->token = $this->getAccessToken($_ENV['WEB_LOGIN'], $_ENV['WEB_PASS']);
      } else {
        $this->token = $this->getAccessToken($this->authData['username'], $this->authData['password']);
      }
    }

    // Headers for the patch curl method containing the access_token
    $headers = [
      "session-token: " . $this->token
    ];

    // Set up the basic and default curl options
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
  }

  /**
   * @param string $user
   * @param string $password
   * @return string
   *  Note : Simply retrieve the access token after auth from FusionDirectory webservice.
   */
  private function getAccessToken (string $user, string $password): string
  {
    // The login endpoint is waiting a json format.
    $loginData = [
      'user'     => $user,
      'password' => $password
    ];

    $ch = curl_init($this->URL);

    // We require to set a proper http_agent
    $os              = PHP_OS;
    $phpVersion      = PHP_VERSION;
    $customUserAgent = "FusionDirectory/Integrator ($os; PHP $phpVersion; https://www.fusiondirectory.org) WebserviceLib";

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_USERAGENT, $customUserAgent);

    $response = curl_exec($ch);
    $this->handleCurlError($ch);

    curl_close($ch);

    // Token should be decoded, to remove quotes.
    return json_decode($response);
  }

  private function handleCurlError ($ch): void
  {
    // String is returned on success but a boolean on error.
    if (curl_error($ch)) {
      $error = [
        'Error'  => 'Error during process of authentication to FusionDirectory web-service!',
        'Status' => curl_errno($ch),
      ];
      echo json_encode($error, JSON_PRETTY_PRINT);
      exit;
    }
  }

  /**
   * @param string $dn
   * Note : receive the DN of the main task and execute it, creating related sub-tasks.
   */
  public function activateCyclicTasks (string $dn)
  {
    $data = [
      "tasks" => [
        "fdSubTasksActivation" => "TRUE"
      ]
    ];

    // the DN can contain space which must be URL encoded correctly.
    $this->setCurlSettings($_ENV['FUSION_DIRECTORY_API_URL'] . '/objects/tasks/' . rawurlencode($dn), $data, 'PATCH');
    curl_exec($this->ch);

    $this->handleCurlError($this->ch);
    $response = json_decode(curl_multi_getcontent($this->ch), TRUE);

    // Manage the response from current FD WebService, returned DN seems to mean success.
    if ($response === $dn) {
      $response = 'Task has been activated successfully';
    }

    curl_close($this->ch);
    return $response;
  }

  /**
   * @param string $dn
   * @return mixed|string
   * Note : When life cycle is triggered, supann status are updated through LDAP, FD must now trigger updates
   */
  public function refreshUserInfo (string $dn)
  {
    // Create a specific array which will be interpreted by setCurlSettings in order to change it to an empty json data.
    $data = [
      'refreshUser' => NULL
    ];

    // A DN can contain space and therefore must be encoded properly before processing.
    $this->setCurlSettings($_ENV['FUSION_DIRECTORY_API_URL'] . '/objects/user/' . rawurlencode($dn), $data, 'PATCH');
    curl_exec($this->ch);

    $this->handleCurlError($this->ch);
    $response = json_decode(curl_multi_getcontent($this->ch), TRUE);

    if ($response === $dn) {
      $response = 'User: ' . $dn . ' has been correctly refreshed.';
    }

    curl_close($this->ch);
    return $response;
  }
}

