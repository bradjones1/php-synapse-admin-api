<?php

declare(strict_types=1);

namespace BradJones1\SynapseAdmin;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class SynapseAdminHttpApi
 *
 * @see https://github.com/matrix-org/synapse/tree/master/docs/admin_api
 *
 * @package BradJones1\SynapseAdmin
 */
class SynapseAdminHttpApi {

  /**
   * HTTP client.
   *
   * @var \Psr\Http\Client\ClientInterface
   */
  protected $httpClient;

  /**
   * Request factory.
   *
   * @var \Psr\Http\Message\RequestFactoryInterface
   */
  protected $requestFactory;

  /**
   * Authorization string for admin user.
   *
   * @var string
   */
  protected $token;

  /**
   * Base URL of homeserver.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * Constructor.
   *
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   HTTP client.
   * @param \Psr\Http\Message\RequestFactoryInterface $requestFactory
   *   HTTP request factory.
   * @param string $baseUrl
   *   Base URL for homeserver.
   * @param string $token
   *   Authorization string for admin user.
   */
  public function __construct(ClientInterface $httpClient, RequestFactoryInterface $requestFactory, string $baseUrl, string $token) {
    $this->httpClient = $httpClient;
    $this->requestFactory = $requestFactory;
    $this->baseUrl = $baseUrl;
    $this->token = $token;
  }

  /**
   * Send a request to the API.
   *
   * @param string $method
   *   HTTP method.
   * @param string $relativeUrl
   *   Relative URL from the base, in the form of [version]/api
   * @param string|NULL $payload
   *   The payload to send.
   *
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Psr\Http\Client\ClientExceptionInterface
   */
  protected function send(string $method, string $relativeUrl, ?string $payload = NULL): ResponseInterface {
    $request = $this->requestFactory
      ->createRequest($method, $this->baseUrl . '/_synapse/admin/' . $relativeUrl);
    if ($payload) {
      $request->getBody()->write($payload);
    }
    return $this->httpClient->sendRequest(
      $request
        ->withAddedHeader('authorization', 'Bearer ' . $this->token)
    );
  }

  /**
   * Register a user noninteractively.
   *
   * @see https://github.com/matrix-org/synapse/blob/master/docs/admin_api/register_api.rst
   */
  public function registerUser(string $registrationSharedSecret, string $username, string $password, bool $admin = FALSE, string $displayName = '') {
    // Fetch nonce.
    $nonceResponse = $this->send('GET', 'v1/register');
    $nonce = json_decode($nonceResponse->getBody()->getContents(), TRUE)['nonce'];
    $mac = $this->getHmacHexDigest([
      $nonce,
      $username,
      $password,
      $admin ? 'admin' : 'notadmin',
      // user_type not yet implemented.
    ], $registrationSharedSecret);
    $payload = [
      'nonce' => $nonce,
      'username' => $username,
      'displayname' => $displayName,
      'password' => $password,
      'admin' => $admin,
      'mac' => $mac,
    ];
    $response = $this->send('POST', 'v1/register', json_encode($payload));
    $text = $response->getBody()->getContents();
  }

  /**
   * Return a SHA256 hex HMAC digest of a payload.
   *
   * @param array $payload
   *   Elements of the hash, as defined by the spec.
   * @param string $key
   *   Key.
   *
   * @return string
   *   Hex digest.
   */
  protected function getHmacHexDigest(array $payload, string $key): string {
    return hash_hmac('sha1', implode("\0", $payload), $key);
  }

}
