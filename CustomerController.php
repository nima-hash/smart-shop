<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\OrderRepository;


final class CustomerController extends AbstractController
{
    #[Route('/customer', name: 'app_customer')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        // Fetch this month's bestsellers 
        $bestsellersOfMonth = $productRepository->findBestsellersOfMonth(4);

        // Fetch this week's bestsellers
        $thisWeeksBestsellers = $productRepository->findBy(['isThisWeeksBestseller' => true], ['id' => 'ASC'], 4);

        $categories = $categoryRepository->findAll();
        $uniqueBrands = $productRepository->findUniqueBrands();
        $uniqueTags = $productRepository->findUniqueTags();
        $featuredProducts = $productRepository->findRandomProducts(4);


        return $this->render('home/index.html.twig', [
            'bestsellersOfMonth' => $bestsellersOfMonth,
            'thisWeeksBestsellers' => $thisWeeksBestsellers,
            'categories' => $categories,
            'brands' => $uniqueBrands,
            'tags' => $uniqueTags,
            'featuredProducts' => $featuredProducts
        ]);
    }

    #[Route('/customer/view/products', name: 'app_customer_viewAllProducts')]
    public function listProducts( ProductRepository $productRepository, CategoryRepository $categoryRepository, PaginatorInterface $paginator, Request $request): Response 
    {



        // Paginate the results
        $pagination = $paginator->paginate(
            $productRepository->findAll(),
            $request->query->getInt('page', 1), // Current page number
            12 // Items per page
        );

        $categories = $categoryRepository->findAll();
        $brands = $productRepository->findUniqueBrands();
        $tags = $productRepository->findUniqueTags();

        return $this->render('customer/product/list.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'searchTerm' => null,
            'brands' => $brands,
            'tags' => $tags,
            'sidebar' => 'filter',
        ]);
    }

    #[Route('/customer/category/{id}', name: 'app_customer_category')]
    public function listProductsInCategory( ProductRepository $productRepository,
                                                CategoryRepository $categoryRepository, 
                                                PaginatorInterface $paginator, 
                                                Request $request, 
                                                int $id
                                                ) : Response 
    {


        $currentCategory = $categoryRepository->find($id);

        if (!$currentCategory) {
            throw $this->createNotFoundException('The category does not exist');
        }

        $queryBuilder = $productRepository->createQueryBuilder('p')
            ->where('p.category = :category')
            ->setParameter('category', $currentCategory)
            ->orderBy('p.createdAt', 'DESC');

        // Paginate the results
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1), // Current page number
            12 // Items per page
        );

        $categories = $categoryRepository->findAll();
        $brands = $productRepository->findUniqueBrands();
        $tags = $productRepository->findUniqueTags();

        return $this->render('customer/product/list.html.twig', [
            'pagination' => $pagination,
            'currentCategory' => $currentCategory,
            'categories' => $categories,
            'searchTerm' => null,
            'brands' => $brands,
            'tags' => $tags,
            'sidebar' => 'filter',
        ]);
    }

    #[Route('/customer/view/products/filter', name: 'app_customer_filter_products')]
    public function filter( ProductRepository $productRepository, CategoryRepository $categoryRepository, PaginatorInterface $paginator, Request $request, ?Category $category = null): Response 
    {

        $filters = $request->query->all();
        $queryBuilder = $productRepository->findFilteredProducts($filters);

        // Paginate the results
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            9 // items per page
        );

        $brands = $productRepository->findUniqueBrands();
        $tags = $productRepository->findUniqueTags();
        $categories = $categoryRepository->findAll();
        $searchTerm = null;
        if (isset($filters['q']) && !empty($filters['q'])) {
            $searchTerm = $filters['q'];
        }

        $products = $productRepository->findFilteredProducts($filters);
        return $this->render('customer/product/list.html.twig', [
            'pagination' => $pagination,

            'searchTerm' => $searchTerm,
            'categories' => $categories,
            'brands' => $brands,
            'tags' => $tags,
            'sidebar' => 'filter',
        ]);
    }

    #[Route('/products/search', name: 'app_customer_product_search')]
    public function searchProducts(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        PaginatorInterface $paginator
    ): Response
    {
        // Get the search term from the query string
        $searchTerm = $request->query->get('q');

        if (!$searchTerm) {
            return $this->redirectToRoute('app_customer_viewAllProducts');
        }

        // Create a QueryBuilder to search for products
        $queryBuilder = $productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->where('p.name LIKE :searchTerm')
            ->orWhere('p.description LIKE :searchTerm')
            ->orWhere('p.tags LIKE :searchTerm')
            ->orWhere('c.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->distinct();

        // Paginate the search results
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            12
        );

        // Fetch all categories, brands, and tags for the sidebar
        $categories = $categoryRepository->findAll();
        $brands = $productRepository->findUniqueBrands();
        $tags = $productRepository->findUniqueTags();

        // Render the list template with search results and all sidebar variables
        return $this->render('customer/product/list.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'brands' => $brands,
            'tags' => $tags,
            'searchTerm' => $searchTerm,
            'currentCategory' => null,
            'sidebar' => 'filter',
        ]);
    }

    #[Route('/customer/product/{slug}', name: 'app_customer_product_show')]
    public function showProduct(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response
    {
        if (!$product->getIsPublished()) { 
            throw $this->createNotFoundException('The product does not exist or is not available.');
        }

        return $this->render('customer/product/show.html.twig', [
            'product' => $product,
        ]);
    }


}
