<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\DoctrineBundle\DoctrineBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new JMS\SecurityExtraBundle\JMSSecurityExtraBundle(),
            new Mapbender\CoreBundle\MapbenderCoreBundle(),
            new Mapbender\WmcBundle\MapbenderWmcBundle(),
            new Mapbender\WmsBundle\MapbenderWmsBundle(),
            new Mapbender\WmtsBundle\MapbenderWmtsBundle(),
            new Mapbender\ManagerBundle\MapbenderManagerBundle(),
            new Arsgeografica\EntityPagerBundle\ArsgeograficaEntityPagerBundle(),
            /**
             * Disable the demo bundle when startin a real-world project!
             */
            new Acme\DemoBundle\AcmeDemoBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
            $bundles[] = new BCC\ExtraToolsBundle\BCCExtraToolsBundle();
            //$bundles[] = new Symfony\Bundle\DoctrineMigrationsBundle\DoctrineMigrationsBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }
}