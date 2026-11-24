<?php

namespace Sil\Idp\IdBroker\Client;

use GuzzleHttp\Command\Exception\CommandException;

/**
 * Thrown when the parameters given to a client method fail validation against
 * the API's service description, before any request is sent to the ID Broker
 * (e.g. "Validation errors: [username] is a required string").
 *
 * This extends Guzzle's CommandException (which is what was thrown in this
 * situation before this class existed), so existing code that catches
 * CommandException still works.
 */
class ValidationException extends CommandException
{
}
