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
   * @var string|NULL
   */
  protected $token = NULL;

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
   * @param string|NULL $token
   *   Authorization string for admin user.
   */
  public function __construct(ClientInterface $httpClient, RequestFactoryInterface $requestFactory, string $baseUrl, ?string $token = NULL) {
    $this->httpClient = $httpClient;
    $this->requestFactory = $requestFactory;
    $this->baseUrl = $baseUrl;
    if ($token) {
      $this->setAdminAccessToken($token);
    }
  }

  /**
   * Set the access token.
   *
   * @param string $token
   */
  public function setAdminAccessToken(string $token): void {
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
    assert(!($method === 'GET' && !is_null($payload)), 'GET is incompatible with payload.');
    $request = $this->requestFactory
      ->createRequest($method, $this->baseUrl . '/_synapse/admin/' . $relativeUrl);
    if ($payload) {
      $request->getBody()->write($payload);
    }
    if ($this->token) {
      $request = $request
        ->withAddedHeader('authorization', 'Bearer ' . $this->token);
    }
    $response = $this->httpClient->sendRequest($request);
    if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 500) {
      $returnedContent = $response->getBody()->getContents();
      $json = json_decode($returnedContent, TRUE);
      $message = !empty($json['error'])
        ? $json['error']
        : $response->getReasonPhrase();
      throw new ClientException($message, $response->getStatusCode());
    }
    return $response;
  }

  /**
   * Register a user noninteractively.
   *
   * @see https://github.com/matrix-org/synapse/blob/master/docs/admin_api/register_api.rst
   *
   * @param string $registrationSharedSecret
   *   Registration shared secret.
   * @param string $username
   *   Username.
   * @param string $password
   *   Password.
   * @param bool $admin
   *   Should be admin?
   * @param string $displayName
   *   Display name.
   *
   * @return array
   *   Array of returned user data.
   *
   * @throws \Psr\Http\Client\ClientExceptionInterface
   */
  public function registerUser(string $registrationSharedSecret, string $username, string $password, bool $admin = FALSE, string $displayName = ''): array {
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
    return json_decode($response->getBody()->getContents(), TRUE);
  }

  /**
   * Query user information.
   *
   * @param string $userId
   *   User ID, e.g. "@admin:localhost"
   *
   * @return array
   *   Array of user data.
   *
   * @throws \Psr\Http\Client\ClientExceptionInterface
   */
  public function queryUser(string $userId): array {
    $returned = $this->send('GET', "v2/users/$userId");
    return json_decode($returned->getBody()->getContents(), TRUE);
  }

  /**
   * Query room members.
   *
   * @param string $roomId
   *   Room ID.
   *
   * @return array
   *   Array of room members data (members and total.)
   *
   * @throws \Psr\Http\Client\ClientExceptionInterface
   */
  public function queryRoomMembers(string $roomId): array {
    $returned = $this->send('GET', "v1/rooms/$roomId/members");
    return json_decode($returned->getBody()->getContents(), TRUE);
  }

  /**
   * Join a user to a room.
   *
   * @param string $roomId
   *   Room ID or alias.
   * @param string $userId
   *   User ID.
   *
   * @throws \Psr\Http\Client\ClientExceptionInterface
   */
  public function joinUserToRoom(string $roomId, string $userId): void {
    $returned = $this->send(
      'POST',
      "v1/join/$roomId",
      json_encode(['user_id' => $userId])
    );
  }

  /**
   * Return a SHA1 hex HMAC digest of a payload.
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
