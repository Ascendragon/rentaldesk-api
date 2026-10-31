<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Exception\InventoryCodeAlreadyExistsException;
use App\Exception\InventoryItemNotFoundException;
use App\Exception\RentalOrderNotEditableException;
use App\Exception\RentalRateNotConfiguredException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event:KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $violations = null;
        $previous = $exception->getPrevious();
        $headers = $exception instanceof HttpExceptionInterface
            ? $exception->getHeaders()
            : [];

        $headers['Content-Type'] = 'application/problem+json';

        if ($exception instanceof InventoryItemNotFoundException) {
            $status = 404;
            $title = 'Not Found';
            $detail = 'Inventory item not found.';
            $code = 'inventory_item_not_found';
        } elseif ($exception instanceof InventoryCodeAlreadyExistsException) {
            $status = 409;
            $title = 'Conflict';
            $detail = 'Inventory code already exists.';
            $code = 'inventory_code_conflict';

        } elseif ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 422 &&
            $previous instanceof ValidationFailedException) {
            $status = 422;
            $title = 'Unprocessable Content';
            $detail = 'Request validation failed.';
            $code = 'validation_failed';

            $violations = [];

            foreach ($previous->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }
        } elseif ($exception instanceof RentalOrderNotEditableException) {
            $status = 409;
            $title = 'Conflict';
            $detail = 'Cannot modify a non-draft order.';
            $code = 'rental_order_not_editable';
        } elseif ($exception instanceof RentalRateNotConfiguredException) {
            $status = 409;
            $title = 'Conflict';
            $detail = 'Rental rate is not configured.';
            $code = 'rental_rate_not_configured';
        }
        elseif (
            $exception instanceof HttpExceptionInterface
            && $exception->getStatusCode() === 400
        ) {
            $status = 400;
            $title = 'Bad Request';
            $detail = 'Malformed request body.';
            $code = 'malformed_request';

        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if ($status >= 500) {
                $title = 'Internal Server Error';
                $detail = 'Unexpected server error.';
                $code = 'internal_error';
            } else {
                $title = Response::$statusTexts[$status] ?? 'HTTP Error';
                $detail = 'HTTP request failed.';
                $code = 'http_error';
            }

        } else {
            $status = 500;
            $title = 'Internal Server Error';
            $detail = 'Unexpected server error.';
            $code = 'internal_error';
        }
        $data = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'code' => $code,
        ];

        if ($violations !== null) {
            $data['violations'] = $violations;
        }

        $response = new JsonResponse(
            $data,
            $status,
            $headers
        );

        $event->setResponse($response);
    }
}
