<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Monitor;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MonitorController extends AbstractController
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    private function monitorToArray(?Monitor $monitor): ?array
    {
        if ($monitor === null) {
            return null;
        }

        return [
            'id'    => $monitor->getId(),
            'name'  => $monitor->getName(),
            'mail'  => $monitor->getMail(),
            'phone' => $monitor->getPhone(),
            'photo' => $monitor->getPhoto(),
        ];
    }

    #[Route('/monitor', name: 'get_monitors', methods: ['GET'])]
    public function getAll(EntityManagerInterface $entityManager): JsonResponse
    {
        $monitors = $entityManager->getRepository(Monitor::class)->findAll();

        // Utiliza array_map con una función anónima para manejar el valor nulo
        $monitorsArray = array_map(
            function ($monitor) {
                return $monitor !== null ? $this->monitorToArray($monitor) : null;
            },
            $monitors
        );

        // Elimina los valores nulos del array (opcional, según tus requisitos)
        $monitorsArray = array_filter($monitorsArray);

        return $this->json($monitorsArray);
    }

    
    #[Route('/monitor', name: 'create_monitor', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
    
        $monitor = new Monitor();
    
        // Check for null values in required fields
        if (!isset($data['name']) || !isset($data['mail']) || !isset($data['phone']) || !isset($data['photo'])) {
            return $this->json(['errors' => ['All fields are required']], JsonResponse::HTTP_BAD_REQUEST);
        }
    
        $monitor->setName($data['name']);
        $monitor->setMail($data['mail']);
        $monitor->setPhone($data['phone']);
        $monitor->setPhoto($data['photo']);
    
        // Validate the entity
        $errors = $validator->validate($monitor);
    
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }
    
        // Persist the entity to the database
        $entityManager->persist($monitor);
        $entityManager->flush();
    
        return $this->json($this->monitorToArray($monitor), JsonResponse::HTTP_CREATED);
    }
    
    
    

  #[Route('/monitor/{id}', name: 'update_monitor', methods: ['PUT'])]
public function update(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, int $id): JsonResponse
{
    $monitor = $entityManager->getRepository(Monitor::class)->find($id);

    if (!$monitor) {
        throw $this->createNotFoundException('Monitor not found');
    }

    $data = json_decode($request->getContent(), true);

    // Update only if the field is present in the JSON data
    if (isset($data['name'])) {
        $monitor->setName($data['name']);
    }
    if (isset($data['mail'])) {
        $monitor->setMail($data['mail']);
    }
    if (isset($data['phone'])) {
        $monitor->setPhone($data['phone']);
    }
    if (isset($data['photo'])) {
        $monitor->setPhoto($data['photo']);
    }

    // Validate the entity
    $errors = $validator->validate($monitor);

    if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
    }

    // Persist the changes to the database
    $entityManager->flush();

    return $this->json($this->monitorToArray($monitor));
}


    #[Route('/monitor/{id}', name: 'delete_monitor', methods: ['DELETE'])]
    public function delete(EntityManagerInterface $entityManager, int $id): JsonResponse
    {
        $monitor = $entityManager->getRepository(Monitor::class)->find($id);

        if (!$monitor) {
            throw $this->createNotFoundException('Monitor not found');
        }

        $entityManager->remove($monitor);
        $entityManager->flush();

        return $this->json(['message' => 'Monitor deleted successfully'], JsonResponse::HTTP_NO_CONTENT);
    }
}
