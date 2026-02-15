<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\UI\Controller;

use App\Integration\Baselinker\Application\Dto\BaselinkerDeepLinkDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BaselinkerDeepLinkController extends AbstractController
{
    public function __construct(private readonly string $panelBaseUrl)
    {
    }

    #[Route('/api/baselinker/deeplink/order/{externalId}', name: 'baselinker_deeplink_order', methods: ['GET'])]
    public function __invoke(string $externalId): JsonResponse
    {
        // construct deep link - configurable base url
        $url = rtrim($this->panelBaseUrl, '/') . '/orders/' . rawurlencode($externalId);

        $dto = new BaselinkerDeepLinkDto($url);

        return new JsonResponse($dto->toArray(), Response::HTTP_OK);
    }
}

