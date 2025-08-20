<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    #[Route('/admin/category', name: 'app_category')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'active_section' => 'Categories'
        ]);
    }

    #[Route('/admin/add/category', name: 'app_category_add')]
    public function new(Request $request, EntityManagerInterface $entityManager, CategoryRepository $categoryRepository): Response
    {
        $category = new Category;

        $form = $this->createForm(CategoryType::class, $category);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category ' . $category->getName() . ' was created.');
            return $this->redirectToRoute('app_category', [
                'active_section' => 'Categories'
            ]);
        }

        
        return $this->render('admin/category/new.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
            'active_section' => 'Categories'

        ]);
    }

    #[Route('/admin/category/{id}', name: 'app_category_show')]
    public function show(Category $category): Response
    {


        
        return $this->render('admin/category/show.html.twig', [
            'category' => $category,
            'active_section' => 'Categories'
        ]);
    }

    #[Route('/admin/category/edit/{id}', name: 'app_category_edit')]
    public function edit(EntityManagerInterface $entityManager, Category $category, Request $request): Response
    {

        $form = $this->createForm(CategoryType::class, $category);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager->flush();

            $this->addFlash('success', 'Category ' . $category->getName() . ' updated successfully.');
            return $this->redirectToRoute('app_category');
        }

        
        return $this->render('admin/category/new.html.twig', [
            'category' => $category,
            'isEdit' => true,
            'form' => $form->createView(),
            'active_section' => 'Categories'
        ]);
    }

    #[Route('/admin/category/delete/{id}', name: 'app_category_delete', methods:['POST'])]
    public function delete(EntityManagerInterface $entityManager, Category $category, Request $request): Response
    {
        $entityManager->remove($category);


            $entityManager->flush();

            $this->addFlash('success', 'Category ' . $category->getName() . ' deleted successfully.');
            return $this->redirectToRoute('app_category', [
                'active_section' => 'Categories'
            ]);

    }
}
