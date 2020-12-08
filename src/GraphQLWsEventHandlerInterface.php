<?php
declare(strict_types=1);

namespace GraphQLWs;

use GraphQL\Language\AST\OperationDefinitionNode;
use Ratchet\WebSocket\WsConnection;

/**
 * Marker interface for event handlers for the subscription server.
 *
 * Use one of the event handlers that extend this instead.
 *
 * @internal
 */
interface GraphQLWsEventHandlerInterface {}
