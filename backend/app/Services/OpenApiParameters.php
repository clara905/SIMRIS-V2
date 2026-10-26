<?php

namespace App\Services;

use App\Http\Requests\ApiRequest;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\In;
use ReflectionNamedType;

class OpenApiParameters implements ParameterExtractor
{
    public function __construct(private TypeTransformer $transformer) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        foreach ($routeInfo->reflectionAction()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof ReflectionNamedType || ! is_a($type->getName(), ApiRequest::class, true)) {
                continue;
            }
            $class = $type->getName();
            $request = new $class;
            $route = clone $routeInfo->route;
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $route->bind(Request::create('/'.$uri, strtoupper($routeInfo->method)));
            $request->setMethod(strtoupper($routeInfo->method));
            $request->setRouteResolver(fn () => $route);
            $rules = $request->rules();
            foreach ($rules as $field => $set) {
                $set = is_array($set) ? $set : explode('|', $set);
                if (in_array('prohibited', $set, true)) {
                    unset($rules[$field]);

                    continue;
                }
                $hasType = count(array_intersect(['string', 'integer', 'numeric', 'boolean', 'array', 'file', 'email', 'date'], array_filter($set, 'is_string'))) > 0;
                $textConstraint = collect($set)->contains(fn ($rule): bool => $rule instanceof In || (is_string($rule) && str_starts_with($rule, 'regex:')));
                if (! $hasType && $textConstraint) {
                    $rules[$field] = ['string', ...$set];
                }
            }
            $parameters = (new RulesToParameters($rules, [], $this->transformer, $routeInfo->method === 'get' ? 'query' : 'body'))->mergeDotNotatedKeys(false)->handle();
            $parameterExtractionResults[] = new ParametersExtractionResult(parameters: $parameters, sourceClass: $class);
        }

        return $parameterExtractionResults;
    }
}
