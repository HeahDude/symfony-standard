<?php

namespace AppBundle\Controller;

use AppBundle\Entity\OtherEntity;
use AppBundle\Entity\TestEntity;
use AppBundle\Entity\YetAnotherEntity;
use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Exception\ExceptionInterface as BlackfireException;
use Blackfire\Profile\Configuration;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @var Client
     */
    private $blackfireClient;

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->warmUp();

        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    private function warmUp()
    {
        if (is_file($this->getParameter('fixtures.loaded'))) {
            return;
        }

        try {
            $em = $this->getDoctrine()->getManager();

            for ($i = 0; $i < 50; ++$i) {
                $em->persist(new TestEntity('Test Entity '.$i, 'test_entity_'.$i));
                $em->persist(new OtherEntity('Other Entity '.$i, 'other_entity_'.$i));
                $em->persist(new YetAnotherEntity('Yet another Entity '.$i, 'yet_another_entity_'.$i));
            }

            $em->flush();

            $this->get('filesystem')->touch($this->getParameter('fixtures.loaded'));

            // Catch any configuration or doctrine exception
        } catch (\Exception $e) {
            throw new \RuntimeException('Could not load fixtures.', 0, $e);
        }
    }

    private function createBlackFireProbe($title = null)
    {
        try {
            if (null === $this->blackfireClient) {
                $parameters = $this->getParameter('blackfire_client');
                $credentials = new ClientConfiguration(
                    $parameters['id'],
                    $parameters['token']
                );
                $this->blackfireClient = new Client($credentials);
            }
            $config = new Configuration();

            $config->setTitle($title ?: date('Y-m-d H:i:s'));

            return $this->blackfireClient->createProbe($config);
        }
        catch (BlackfireException $e)
        {
            throw new \RuntimeException('Failed to start profiling.', 0, $e);
        }
    }

    private function sendProbe($probe)
    {
        try {
            $profile = $this->blackfireClient->endProbe($probe);
        } catch (BlackfireException $e) {
            throw new \RuntimeException('Failed to end profiling.', 0, $e);
        }

        if ($profile->isErrored()) {
            dump('Profile has errors:');
            dump($profile);
        }
    }
}
