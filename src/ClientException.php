<?php

declare(strict_types=1);

namespace BradJones1\SynapseAdmin;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \Exception implements ClientExceptionInterface {

  /**
   * {@inheritDoc}
   */
  #[Pure] public function __construct(
    string $message = "",
    int $code = 0,
    ?Throwable $previous = NULL,
    public readonly ?string $errCode = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

}
