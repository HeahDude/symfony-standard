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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
        // Load fixtures
        $this->warmUp();

        $em = $this->getDoctrine()->getManager();
        $data = array(
            'test' => $e1 = new TestEntity('Pre set test entity', 'pre_set_test_entity'),
            'other' => $e2 = new TestEntity('Pre set other entity', 'pre_set_other_entity'),
            'yet_another' => $e3 = new TestEntity('Pre set yet another entity', 'pre_set_yet_another_entity'),
        );

        $em->persist($e1);
        $em->persist($e2);
        $em->persist($e3);

        $options = array(
            'type' => EntityType::class,
            'choice_options' => array('choice_label' => 'name'),
        );
        $builder = $this->createFormBuilder($data)
            ->add('test', $options['type'], array('class' => TestEntity::class) + $options['choice_options'])
            ->add('other', $options['type'], array('class' => OtherEntity::class) + $options['choice_options'])
            ->add('yet_another', $options['type'], array('class' => OtherEntity::class) + $options['choice_options'])
            ->add('Save', '\Symfony\Component\Form\Extension\Core\Type\SubmitType')
        ;

        $optimized = true;
        $subtitle = ($post = 'POST' === $request->getMethod() ? 'on submit' : 'on init').($optimized ? ' optimized' : '');

        // Start profiling
        $probe = $this->createBlackFireProbe('Form types loading '.$subtitle);

        // Loads the types and creates the lazy choice list
        $form = $builder->getForm();

        if ($post) {
            $this->sendProbe($probe);
            $probe = $this->createBlackFireProbe('Handle request');

            $form->handleRequest($request);
        }

        $this->sendProbe($probe);

        // Start another profiling
        $probe = $this->createBlackFireProbe('ChoiceList loading '.$subtitle);

        // Loads the entity and creates an array choice list
        $view = $form->createView();

        $this->sendProbe($probe);

        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'form' => $view,
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
