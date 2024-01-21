<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\Monitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ActivityController extends AbstractController
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;

    }


    private function activityToArray(Activity $activity): array
{
    $monitorsArray = [];
    
    if ($activity->getMonitors() !== null) {
        foreach ($activity->getMonitors() as $monitor) {
            $monitorsArray[] = [
                'id'    => $monitor->getId(),
                'name'  => $monitor->getName(),
                'mail'  => $monitor->getMail(),
                'phone' => $monitor->getPhone(),
                'photo' => $monitor->getPhoto(),
            ];
        }
    }

    return [
        'id' => $activity->getId(),
        'activitytype_id' => $activity->getActivityTypeId() ? $activity->getActivityTypeId()->getName() : null,
        'dateStart' => $activity->getDateStart() ? $activity->getDateStart()->format('Y-m-d H:i:s') : null,
        'dateEnd' => $activity->getDateEnd() ? $activity->getDateEnd()->format('Y-m-d H:i:s') : null,
        'monitors' => $monitorsArray,
      
    ];
}

    
    



    #[Route('/activity', name: 'get_activities', methods: ['GET'])]
    public function getAll(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $dateParameter = $request->query->get('date');

        $activityRepository = $entityManager->getRepository(Activity::class);

        if ($dateParameter) {
            // Si se proporciona el parámetro de fecha, intenta buscar actividades por esa fecha y hora
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $dateParameter . ' 00:00:00');

            if (!$dateTime) {
                return $this->json(['error' => 'Formato de fecha y hora no válido. Use Y-m-d H:i:s.'], 400);
            }

            $activities = $activityRepository->findBy(['date_start' => $dateTime]);
        } else {
            // Si no se proporciona el parámetro de fecha, obtén todas las actividades
            $activities = $activityRepository->findAll();
        }

        $activitiesArray = [];

        foreach ($activities as $activity) {
            $activitiesArray[] = $activity->toArray();
        }

        return $this->json($activitiesArray);
    }

    #[Route('/activity', name: 'create_activity', methods: ['POST'])]
public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $activity = new Activity();

    // Validate that the activity type is provided and exists in the database
    if (empty($data['activity_type_id'])) {
        return $this->json(['error' => 'El tipo de actividad es obligatorio.'], 400);
    }

    $activityType = $entityManager->getRepository(ActivityType::class)->find($data['activity_type_id']);

    if (!$activityType) {
        return $this->json(['error' => 'Tipo de actividad no encontrado.'], 400);
    }

    // Validate the date and time
    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $data['date_start']);
    $validStartTimes = ['09:00', '13:30', '17:30'];

    if (!$date || !in_array($date->format('H:i'), $validStartTimes)) {
        return $this->json(['error' => 'La fecha de inicio no es válida. Debe ser a las 09:00, 13:30 o 17:30.'], 400);
    }

    // Validate the duration (90 minutes)
    $dateEnd = clone $date;
    $dateEnd->modify('+90 minutes');
    $validEndTimes = ['10:30', '15:00', '18:30'];

    if (!in_array($dateEnd->format('H:i'), $validEndTimes)) {
        return $this->json(['error' => 'La duración no es válida. Debe ser de 90 minutos.'], 400);
    }

    $activity->setActivityTypeId($activityType);
    $activity->setDateStart($date);
    $activity->setDateEnd($dateEnd);

    // Validate the number of monitors against number_monitors in activity_type
    $expectedMonitors = $activityType->getNumberMonitors();
    $actualMonitors = count($data['monitors']);

    if ($actualMonitors !== $expectedMonitors) {
        return $this->json(['error' => "Se esperan $expectedMonitors monitores para esta actividad."], 400);
    }

    // Validate monitors
    if (empty($data['monitors'])) {
        return $this->json(['error' => 'Se requiere al menos un monitor para la actividad.'], 400);
    }

    foreach ($data['monitors'] as $monitorId) {
        $monitor = $entityManager->getRepository(Monitor::class)->find($monitorId);

        if (!$monitor) {
            return $this->json(['error' => 'Monitor no encontrado.'], 400);
        }

        // Validate that the monitor meets the requirements of the activity type
        if (!$monitor->getActivities()->isEmpty()) {
            foreach ($monitor->getActivities() as $monitorActivity) {
                if ($monitorActivity->getDateStart() < $dateEnd && $monitorActivity->getDateEnd() > $date) {
                    return $this->json(['error' => 'El monitor ya está asignado en ese horario.'], 400);
                }
            }
        }

        $activity->addMonitor($monitor);
    }

    // Validate entity with ValidatorInterface
    $errors = $this->validator->validate($activity);

    if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], 400);
    }

    $entityManager->persist($activity);
    $entityManager->flush();

    return $this->json($this->activityToArray($activity));
}


#[Route('/activity/{id}', name: 'update_activity', methods: ['PUT'])]
public function update(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
{
 
    $activity = $entityManager->getRepository(Activity::class)->find($id);

   
    if (!$activity) {
        return $this->json(['error' => 'Activity not found.'], 404);
    }

 
    $data = json_decode($request->getContent(), true);

    // Validate if the activity type is provided and exists in the database
    if (!empty($data['activity_type_id'])) {
        $activityType = $entityManager->getRepository(ActivityType::class)->find($data['activity_type_id']);

      
        if (!$activityType) {
            return $this->json(['error' => 'Activity type not found.'], 400);
        }

        
        $activity->setActivityTypeId($activityType);
    }

    // Validate if the date is provided and adheres to the requirements
    if (!empty($data['date_start'])) {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $data['date_start']);

        
        if (!$date || !in_array($date->format('H:i'), ['09:00', '13:30', '17:30'])) {
            return $this->json(['error' => 'Invalid start date. It should be at 09:00, 13:30, or 17:30.'], 400);
        }

        // Validate the duration (90 minutes)
        $dateEnd = clone $date;
        $dateEnd->modify('+90 minutes');

        
        if (!in_array($dateEnd->format('H:i'), ['10:30', '15:00', '18:30'])) {
            return $this->json(['error' => 'Invalid duration. It should be 90 minutes.'], 400);
        }

        
        $activity->setDateStart($date);
        $activity->setDateEnd($dateEnd);
    }

    // Validate if the number of monitors matches the number_monitors in activity_type
    if (!empty($data['monitors'])) {
        $activityType = $activity->getActivityTypeId();
        $expectedMonitors = $activityType->getNumberMonitors();

   
        if (count($data['monitors']) !== $expectedMonitors) {
            return $this->json(['error' => "Expected $expectedMonitors monitors for this activity."], 400);
        }
    }

    // Validate monitors
    if (!empty($data['monitors'])) {
        foreach ($activity->getMonitors() as $monitor) {
            $activity->removeMonitor($monitor);
        }

        foreach ($data['monitors'] as $monitorId) {
            $monitor = $entityManager->getRepository(Monitor::class)->find($monitorId);

            
            if (!$monitor) {
                return $this->json(['error' => 'Monitor not found.'], 400);
            }

            // Validate that the monitor meets the requirements of the activity type
            if (!$monitor->getActivities()->isEmpty()) {
                foreach ($monitor->getActivities() as $monitorActivity) {
                    if ($monitorActivity->getDateStart() < $dateEnd && $monitorActivity->getDateEnd() > $date) {
                        return $this->json(['error' => 'The monitor is already assigned during that time.'], 400);
                    }
                }
            }

            $activity->addMonitor($monitor);
        }
    }

    // Validate the entity with the ValidatorInterface
    $errors = $this->validator->validate($activity);

    // Return errors if any validation errors occur
    if (count($errors) > 0) {
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }
        return $this->json(['errors' => $errorMessages], 400);
    }

  
    $entityManager->flush();

   
    return $this->json($activity->toArray());
}


    #[Route('/activity/{id}', name: 'delete_activity', methods: ['DELETE'])]
    public function delete($id, EntityManagerInterface $entityManager): JsonResponse
    {
        $activity = $entityManager->getRepository(Activity::class)->find($id);

        if (!$activity) {
            return $this->json(['error' => 'Actividad no encontrada.'], 404);
        }

        $entityManager->remove($activity);
        $entityManager->flush();

        return $this->json(['message' => 'Actividad eliminada con éxito.']);
    }
}