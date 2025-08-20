<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;

    public function __construct(ProductRepository $productRepository, CategoryRepository $categoryRepository)
    {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }


    #[Route('/', name: 'app_home')]
    public function index1(): Response
    {
        // this month's bestsellers
        $bestsellersOfMonth = $this->productRepository->findBestsellersOfMonth(4);

        // this week's bestsellers
        $thisWeeksBestsellers = $this->productRepository->findBy(['isThisWeeksBestseller' => true], ['id' => 'ASC'], 4);

        $categories = $this->categoryRepository->findAll();
        $uniqueBrands = $this->productRepository->findUniqueBrands();
        $uniqueTags = $this->productRepository->findUniqueTags();
        $featuredProducts = $this->productRepository->findRandomProducts(4);


        return $this->render('home/index.html.twig', [
            'bestsellersOfMonth' => $bestsellersOfMonth,
            'thisWeeksBestsellers' => $thisWeeksBestsellers,
            'categories' => $categories,
            'brands' => $uniqueBrands,
            'tags' => $uniqueTags,
            'featuredProducts' => $featuredProducts
        ]);
    }
}
