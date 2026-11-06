<?php

namespace addons\mcp\lib;

use Mcp\Server\Auth\TokenValidationResult;
use Mcp\Server\Auth\TokenValidatorInterface;

class AdminPathTokenValidator implements TokenValidatorInterface
{
    /** @var string */
    private $adminPath;

    public function __construct(string $adminPath)
    {
        $this->adminPath = $adminPath;
    }

    public function validate(string $token): TokenValidationResult
    {
        if ($this->adminPath !== '' && hash_equals($this->adminPath, $token)) {
            return new TokenValidationResult(true, [
                'scope' => 'mcp',
                'sub' => 'admin-path',
            ]);
        }

        return new TokenValidationResult(false, [], 'invalid_token');
    }
}
