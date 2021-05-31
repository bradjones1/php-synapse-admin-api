<?php

declare(strict_types=1);

namespace BradJones1\SynapseAdmin;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \Exception implements ClientExceptionInterface {}
