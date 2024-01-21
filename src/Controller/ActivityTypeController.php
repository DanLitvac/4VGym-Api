<?php

namespace App\Controller;

use App\Entity\ActivityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ActivityTypeController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/activityType', name: 'get_activityTypes', methods: ['GET'])]
    public function getAllActivityTypes(): JsonResponse
    {
        $activityTypes = $this->entityManager->getRepository(ActivityType::class)->findAll();

        return $this->json(array_map(fn($type) => $type->toArray(), $activityTypes));
    }

}
