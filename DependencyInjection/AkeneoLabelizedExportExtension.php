<?php

namespace Niji\AkeneoLabelizedExportBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class AkeneoLabelizedExportExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('normalizers.yml');
        $loader->load('processors.yml');
        $loader->load('writers.yml');
        $loader->load('jobs.yml');
        $loader->load('job_defaults.yml');
        $loader->load('job_constraints.yml');
        $loader->load('steps.yml');
        $loader->load('providers.yml');
    }
}
