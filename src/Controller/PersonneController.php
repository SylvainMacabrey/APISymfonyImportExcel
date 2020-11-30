<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Repository\PersonneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PersonneController extends AbstractController
{

    /**
     * @Route("/personne", name="personne.index", methods="GET")
     */
    public function index(PersonneRepository $personneRepository): Response
    {
        $personnes = $personneRepository->findAll();
        return $this->json($personnes, 200, [], ['groups' => 'personne:read']);
    }

    /**
    * @Route("/personne", name="personne.add", methods="POST")
    */
    public function add(EntityManagerInterface $entityManager, Request $request, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $contenu = $request->getContent();
        try {
            $personne = $serializer->deserialize($contenu, Personne::class, 'json');
            $errors = $validator->validate($personne);
            if (count($errors) > 0) {
                return $this->json($errors, 400);
            }
            $entityManager->persist($personne);
            $entityManager->flush();
            return $this->json($personne, 201, [], ['groups' => 'personne:read']);
        } catch (NotEncodableValueException $e) {
            return $this->json(['status' => 400, 'message' => $e->getMessage()]);
        }
    }

    /**
    * @Route("/personne/{id}", name="personne.edit", methods="PUT")
    */
    public function edit(EntityManagerInterface $entityManager, Request $request, PersonneRepository $personneRepository, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $contenu = $serializer->deserialize($request->getContent(), Personne::class, 'json');
        $personne = $personneRepository->find($request->get('id'));
        if (empty($personne)) {
            return $this->json(['status' => 404, 'message' => 'Personne not found']);
        }
        try {
            $errors = $validator->validate($personne);
            if (count($errors) > 0) {
                return $this->json($errors, 400);
            }
            $personne->setNom($contenu->getNom());
            $personne->setPrenom($contenu->getPrenom());
            $entityManager->persist($personne);
            $entityManager->flush();
            return $this->json($personne, 201, [], ['groups' => 'personne:read']);
        } catch (NotEncodableValueException $e) {
            return $this->json(['status' => 400, 'message' => $e->getMessage()]);
        }
    }

    /**
    * @Route("/personne/{id}", name="personne.delete", methods="DELETE")
    */
    public function delete(EntityManagerInterface $entityManager, Request $request, PersonneRepository $personneRepository)
    {
        $personne = $personneRepository->find($request->get('id'));
        if (empty($personne)) {
            return $this->json(['status' => 404, 'message' => 'Personne not found']);
        }
        try {
            $entityManager->remove($personne);
            $entityManager->flush();
            return $this->json(['status' => 201, 'message' => 'personne delete']);
        } catch (NotEncodableValueException $e) {
            return $this->json(['status' => 400, 'message' => $e->getMessage()]);
        }
    }

    /**
    * @Route("/personne/xlsx", name="personne.xlsx", methods="POST")
    */
    public function xslx(EntityManagerInterface $entityManager, Request $request, PersonneRepository $personneRepository)
    {
        $file = $request->files->get('file');
        $fileFolder = __DIR__ . '/../../public/uploads/';
        $filePathName = md5(uniqid()) . $file->getClientOriginalName();
        try {
            $file->move($fileFolder, $filePathName);
        } catch (FileException $e) {
            dd($e);
        }
        $spreadsheet = IOFactory::load($fileFolder . $filePathName); 
        $row = $spreadsheet->getActiveSheet()->removeRow(1); 
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $entityManager = $this->getDoctrine()->getManager(); 
        foreach ($sheetData as $Row) { 
            $nom = $Row['A'];
            $prenom = $Row['B'];
            $personne_existant = $personneRepository->findOneBy(array('nom' => $nom, 'prenom' => $prenom)); 
            if (!$personne_existant) {   
                $personne = new Personne(); 
                $personne->setNom($nom);           
                $personne->setPrenom($prenom);
                $entityManager->persist($personne); 
                $entityManager->flush(); 
            } 
        } 
        return $this->json('Personnes registered', 200); 
    }

}
