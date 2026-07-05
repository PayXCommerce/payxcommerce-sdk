<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use PayXCommerce\Payment\Model\Logger;
use PayXCommerce\Payment\Model\Webhook\Processor;
use PayXCommerce\Payment\Model\Webhook\Verifier;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly Verifier $verifier,
        private readonly Processor $processor,
        private readonly Logger $logger
    ) {
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $rawBody = (string) $this->request->getContent();
        $eventId = (string) $this->request->getHeader('X-PXC-Event-ID');
        $timestamp = (string) $this->request->getHeader('X-PXC-Timestamp');
        $signature = (string) $this->request->getHeader('X-PXC-Signature');
        $storeId = null;

        $decoded = json_decode($rawBody, true);
        if (is_array($decoded) && isset($decoded['metadata']['store_id'])) {
            $storeId = (int) $decoded['metadata']['store_id'];
        }

        try {
            $payload = $this->verifier->verify($eventId, $timestamp, $signature, $rawBody, $storeId);
        } catch (\Throwable $exception) {
            $this->logger->error('Webhook verification failed: ' . $exception->getMessage());
            return $result->setHttpResponseCode(401)->setContents('Invalid signature');
        }

        try {
            $message = $this->processor->process($payload, $eventId);
            $code = str_starts_with($message, 'Accepted') ? 202 : 200;
            return $result->setHttpResponseCode($code)->setContents($message);
        } catch (\Throwable $exception) {
            $this->logger->error('Webhook processing failed: ' . $exception->getMessage());
            return $result->setHttpResponseCode(500)->setContents('Processing failed');
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
