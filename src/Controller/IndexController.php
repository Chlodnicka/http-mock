<?php

namespace InterNations\Component\HttpMock\Controller;

use InterNations\Component\HttpMock\StorageService;
use InterNations\Component\HttpMock\Util;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndexController extends AbstractController
{
    public function deleteExpectations(StorageService $storageService, Request $request): Response
    {
        $storageService->delete($request, 'expectations');
        return new Response('', Response::HTTP_OK);
    }

    public function addExpectation(StorageService $storageService, Request $request): Response
    {
        $matcher = [];

        if ($request->request->has('matcher')) {
            $matcher = Util::silentDeserialize($request->request->get('matcher'));
            $validator = static function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                return new Response(
                    'POST data key "matcher" must be a serialized list of closures',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }
        }

        if (!$request->request->has('response')) {
            return new Response('POST data key "response" not found in POST data', Response::HTTP_EXPECTATION_FAILED);
        }

        $response = Util::silentDeserialize($request->request->get('response'));

        if (!$response instanceof Response) {
            return new Response(
                'POST data key "response" must be a serialized Symfony response',
                Response::HTTP_EXPECTATION_FAILED
            );
        }

        $limiter = null;

        if ($request->request->has('limiter')) {
            $limiter = Util::silentDeserialize($request->request->get('limiter'));

            if (!is_callable($limiter)) {
                return new Response(
                    'POST data key "limiter" must be a serialized closure',
                    Response::HTTP_EXPECTATION_FAILED
                );
            }
        }

        // Fix issue with silex default error handling
        $response->headers->set('X-Status-Code', $response->getStatusCode());

        $storageService->prepend(
            $request,
            'expectations',
            ['matcher' => $matcher, 'response' => $response, 'limiter' => $limiter, 'runs' => 0]
        );

        return new Response('', Response::HTTP_CREATED);
    }

    public function countRequests(StorageService $storageService, Request $request): int
    {
        return count($storageService->read($request, 'requests'));
    }

    public function getRequest(StorageService $storageService, Request $request, int $index): Response
    {
        $requestData = $storageService->read($request, 'requests');

        if (!isset($requestData[$index])) {
            return new Response('Index ' . $index . ' not found', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestData[$index], Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function deleteRequestAction(StorageService $storageService, Request $request, $action): Response
    {
        $requestData = $storageService->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        $storageService->store($request, 'requests', $requestData);

        if (!$requestString) {
            return new Response($action . ' not possible', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestString, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function getRequestAction(StorageService $storageService, Request $request, $action): Response
    {
        $requestData = $storageService->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' ? 'pop' : 'shift');
        $requestString = $fn($requestData);

        if (!$requestString) {
            return new Response($action . ' not available', Response::HTTP_NOT_FOUND);
        }

        return new Response($requestString, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function deleteRequest(StorageService $storageService, Request $request): Response
    {
        $storageService->store($request, 'requests', []);

        return new Response('', Response::HTTP_OK);
    }

    public function delete(StorageService $storageService, Request $request): Response
    {
        $storageService->store($request, 'requests', []);
        $storageService->store($request, 'expectations', []);

        return new Response('', Response::HTTP_OK);
    }

    public function me(): Response
    {
        return new Response('O RLY?', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}