<?php
/*
 * This file is part of Swagger Mock.
 *
 * (c) Igor Lazarev <strider2038@yandex.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\OpenAPI\Parsing;

use App\Mock\Parameters\MockParametersCollection;

/**
 * @author Igor Lazarev <strider2038@yandex.ru>
 */
class SpecificationParser
{
    /** @var ContextualParserInterface */
    private $endpointParser;

    /** @var ParsingContext */
    private $context;

    public function __construct(ContextualParserInterface $endpointParser)
    {
        $this->endpointParser = $endpointParser;
    }

    public function parseSpecification(array $specification): MockParametersCollection
    {
        $this->initializeContext();
        $this->validateSpecification($specification);

        $collection = new MockParametersCollection();
        $pathsContext = $this->context->withSubPath('paths');

        foreach ($specification['paths'] as $path => $endpoints) {
            $pathContext = $pathsContext->withSubPath($path);
            $this->validateEndpointSpecificationAtPath($endpoints, $pathContext);

            foreach ($endpoints as $httpMethod => $endpointSpecification) {
                $endpointContext = $pathContext->withSubPath($httpMethod);
                $this->validateEndpointSpecificationAtPath($endpointSpecification, $endpointContext);

                $mockParameters = $this->endpointParser->parse($endpointSpecification, $endpointContext);
                $mockParameters->path = $path;
                $mockParameters->httpMethod = strtoupper($httpMethod);
                $collection->add($mockParameters);
            }
        }

        return $collection;
    }

    private function initializeContext(): void
    {
        $this->context = new ParsingContext();
    }

    private function validateSpecification(array $specification): void
    {
        if (!array_key_exists('openapi', $specification)) {
            throw new ParsingException(
                'Cannot detect OpenAPI specification version: tag "openapi" does not exist.',
                $this->context
            );
        }

        if (((int)$specification['openapi']) !== 3) {
            throw new ParsingException(
                'OpenAPI specification version is not supported. Supports only 3.*.',
                $this->context
            );
        }

        if (
            !array_key_exists('paths', $specification)
            || !\is_array($specification['paths'])
            || \count($specification['paths']) === 0
        ) {
            throw new ParsingException('Section "paths" is empty or does not exist', $this->context);
        }
    }

    private function validateEndpointSpecificationAtPath($endpointSpecification, ParsingContext $context): void
    {
        if (!\is_array($endpointSpecification) || \count($endpointSpecification) === 0) {
            throw new ParsingException('Empty or invalid endpoint specification', $context);
        }
    }
}
