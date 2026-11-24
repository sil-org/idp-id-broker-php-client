<?php

namespace Sil\Idp\IdBroker\Client;

use GuzzleHttp\Command\Exception\CommandException;

/**
 * Thrown when the parameters given to a client method fail validation against
 * the API's service description, before any request is sent to the ID Broker
 * (e.g. "Validation errors: [username] is a required string").
 *
 * This extends Guzzle's CommandException, so code that catches
 * CommandException also catches this. The original exception from Guzzle's
 * validator is available via getPrevious().
 */
class ValidationException extends CommandException
{
}
