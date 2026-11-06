<?php

namespace addons\mcp\controller;

require_once __DIR__ . '/../lib/php74.php';
require_once __DIR__ . '/../tool/ProductTools.php';
require_once __DIR__ . '/../service/ProductQueryService.php';
require_once __DIR__ . '/../service/ProductUpdateService.php';
require_once __DIR__ . '/../service/ProductGroupUpdateService.php';

use addons\mcp\service\ProductGroupUpdateService;
use addons\mcp\service\ProductQueryService;
use addons\mcp\service\ProductUpdateService;
use addons\mcp\lib\AdminPathTokenValidator;
use addons\mcp\tool\ProductTools;
use cmf\controller\RestBaseController;
use Mcp\Server\Transport\Http\HttpMessage;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\Server;
use Mcp\Shared\McpError;
use Mcp\Types\MetaKeys;

class McpController extends RestBaseController
{
    const PROTOCOL_VERSION = '2026-07-28';

    public function index()
    {
        if (!$this->request->isPost()) {
            return json([
                'error' => 'Method not allowed',
            ], 405, [
                'Allow' => 'POST',
            ]);
        }

        $rawBody = (string) $this->request->getInput();
        $httpRequest = HttpMessage::fromGlobals();
        $httpRequest->setBody($rawBody);
        $protocolError = $this->ensureModernProtocolOnly($rawBody);
        if ($protocolError !== null) {
            return json($protocolError, 400);
        }

        $adminPath = (string) (config('database.admin_application') ?: 'admin');
        $server = new Server('zjmf-mcp');

        $productTools = new ProductTools(
            new ProductQueryService(),
            new ProductUpdateService(),
            new ProductGroupUpdateService()
        );
        $productTools->register($server);

        $endpoint = rtrim($this->request->domain(), '/') . '/mcp';
        $options = [
            'enable_sse' => false,
            'shared_hosting' => true,
            'auto_detect' => false,
            'server_header' => 'ZJMF-MCP/1.4',
            'auth_enabled' => true,
            'authorization_servers' => [rtrim($this->request->domain(), '/')],
            'resource' => $endpoint,
            'token_validator' => new AdminPathTokenValidator($adminPath),
        ];

        $runner = new HttpServerRunner(
            $server,
            $server->createInitializationOptions(),
            $options
        );

        $response = $runner->handleRequest($httpRequest);
        $runner->sendResponse($response);
        exit;
    }

    /**
     * Reject the legacy initialize/session protocol before the SDK can
     * negotiate it. The 2026-07-28 protocol is stateless and carries its
     * protocol version in each request's _meta envelope.
     */
    private function ensureModernProtocolOnly($raw)
    {
        if (!is_string($raw) || trim($raw) === '') {
            return $this->protocolError(null, -32600, 'Invalid Request', 'Request body must be JSON.');
        }

        $message = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($message)) {
            return $this->protocolError(null, -32600, 'Invalid Request', 'Request body must contain one JSON-RPC message.');
        }

        $id = null;
        if (property_exists($message, 'id') && (is_string($message->id) || is_int($message->id))) {
            $id = $message->id;
        }

        if (!property_exists($message, 'jsonrpc') || $message->jsonrpc !== '2.0') {
            return $this->protocolError($id, -32600, 'Invalid Request', 'jsonrpc must be "2.0".');
        }

        if (!property_exists($message, 'method') || !is_string($message->method) || $message->method === '') {
            return $this->protocolError($id, -32600, 'Invalid Request', 'A JSON-RPC message must contain a method.');
        }

        $params = property_exists($message, 'params') ? $message->params : null;
        $meta = is_object($params) && property_exists($params, '_meta') ? $params->_meta : null;
        if (!is_object($meta)) {
            return $this->protocolError(
                $id,
                McpError::UNSUPPORTED_PROTOCOL_VERSION,
                'Unsupported protocol version',
                [
                    'supported' => [self::PROTOCOL_VERSION],
                    'requested' => null,
                ]
            );
        }

        $requestedVersion = property_exists($meta, MetaKeys::PROTOCOL_VERSION)
            ? $meta->{MetaKeys::PROTOCOL_VERSION}
            : null;
        if ($requestedVersion !== self::PROTOCOL_VERSION) {
            return $this->protocolError(
                $id,
                McpError::UNSUPPORTED_PROTOCOL_VERSION,
                'Unsupported protocol version',
                [
                    'supported' => [self::PROTOCOL_VERSION],
                    'requested' => $requestedVersion,
                ]
            );
        }

        return null;
    }

    private function protocolError($id, $code, $message, $data = null)
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }
}
