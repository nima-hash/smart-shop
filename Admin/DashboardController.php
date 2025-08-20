<?php
namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ProductRepository $productRepository,
    ): Response {
        // Fetch all the data required for the dashboard
        $today = new DateTimeImmutable('today');
        $pendingOrders = $orderRepository->findBy(['status' => 'pending']);
        $totalSales = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
        $totalCustomers = $userRepository->count([]);
        $totalProducts = $productRepository->count([]);
        
        $lowStockProducts = $productRepository->findBy(['isLowStock' => true]); 
        $latestOrders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 5); 
        
        return $this->render('admin/dashboard.html.twig', [
            'pendingOrders' => $pendingOrders,
            'totalSales' => $totalSales,
            'totalCustomers' => $totalCustomers,
            'totalProducts' => $totalProducts,
            'lowStockProducts' => $lowStockProducts,
            'latestOrders' => $latestOrders,
            'active_section' => 'Dashboard',
        ]);
    }
}
?>