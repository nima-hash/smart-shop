<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\KernelInterface;

class MessengerController extends AbstractController
{
    private KernelInterface $kernel;
    private string $cronToken;

    public function __construct(KernelInterface $kernel, string $messengerCronToken)
    {
        $this->kernel = $kernel;
        $this->cronToken = $messengerCronToken;
    }

    #[Route('/run/messenger/consume', name: 'app_run_messenger_consume')]
    public function run(Request $request): Response
    {
        // Check for the security token
        if ($request->query->get('token') !== $this->cronToken) {
            throw $this->createAccessDeniedException('Invalid cron token.');
        }

        $projectDir = $this->kernel->getProjectDir();
        $process = new Process(['php', 'bin/console', 'messenger:consume', 'async', '--limit=10', '--memory-limit=128M', '--time-limit=10', '--sleep=0.1', '--env=prod', '--no-interaction']);
        $process->setWorkingDirectory($projectDir);
        // Run the process
        try {
            $process->run();


            return new Response(
                '',Response::HTTP_OK
            );

        } catch (\Exception $e) {
            return new Response('An error occurred: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}