<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Admin;

use Knp\Menu\ItemInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Stateless breadcrumbs builder (each method needs an Admin object).
 *
 * @author Grégoire Paris <postmaster@greg0ire.fr>
 */
final class BreadcrumbsBuilder implements BreadcrumbsBuilderInterface
{
    /**
     * @var string[]
     */
    private array $config = [];

    /**
     * @param string[] $config
     */
    public function __construct(array $config = [])
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->config = $resolver->resolve($config);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'child_admin_route' => 'show',
        ]);
    }

    public function getBreadcrumbs(AdminInterface $admin, string $action): array
    {
        $breadcrumbs = [];
        if ($admin->isChild()) {
            return $this->getBreadcrumbs($admin->getParent(), $action);
        }

        $menu = $this->buildBreadcrumbs($admin, $action);

        do {
            $breadcrumbs[] = $menu;
        } while ($menu = $menu->getParent());

        $breadcrumbs = array_reverse($breadcrumbs);
        array_shift($breadcrumbs);

        return $breadcrumbs;
    }

    /**
     * Builds breadcrumbs for $action, starting from $menu.
     *
     * Note: the method will be called by the top admin instance (parent => child)
     *
     * @param AdminInterface<object> $admin
     */
    public function buildBreadcrumbs(
        AdminInterface $admin,
        string $action,
        ?ItemInterface $menu = null
    ): ItemInterface {
        if (null === $menu) {
            $menu = $admin->getMenuFactory()->createItem('root');

            $menu = $menu->addChild(
                'link_breadcrumb_dashboard',
                [
                    'uri' => $admin->getRouteGenerator()->generate('sonata_admin_dashboard'),
                    'extras' => ['translation_domain' => 'SonataAdminBundle'],
                ]
            );
        }

        $menu = $this->createMenuItem(
            $admin,
            $menu,
            \sprintf('%s_list', $admin->getClassnameLabel()),
            $admin->getTranslationDomain(),
            [
                'uri' => $admin->hasRoute('list') && $admin->hasAccess('list') ?
                $admin->generateUrl('list') :
                null,
            ]
        );

        $childAdmin = $admin->getCurrentChildAdmin();

        if (null !== $childAdmin && $admin->hasSubject()) {
            $id = $admin->getRequest()->get($admin->getIdParameter());

            $menu = $menu->addChild(
                $admin->toString($admin->getSubject()),
                [
                    'uri' => $admin->hasRoute($this->config['child_admin_route']) && $admin->hasAccess($this->config['child_admin_route'], $admin->getSubject()) ?
                    $admin->generateUrl($this->config['child_admin_route'], [$admin->getIdParameter() => $id]) :
                    null,
                    'extras' => [
                        'translation_domain' => false,
                    ],
                ]
            );

            $menu->setExtra('safe_label', false);

            return $this->buildBreadcrumbs($childAdmin, $action, $menu);
        }

        if ('list' === $action) {
            $menu->setUri(null);

            return $menu;
        }
        if ('create' !== $action && $admin->hasSubject()) {
            return $menu->addChild($admin->toString($admin->getSubject()), [
                'extras' => [
                    'translation_domain' => false,
                ],
            ]);
        }

        return $this->createMenuItem(
            $admin,
            $menu,
            \sprintf('%s_%s', $admin->getClassnameLabel(), $action),
            $admin->getTranslationDomain()
        );
    }

    /**
     * Creates a new menu item from a simple name. The name is normalized and
     * translated with the specified translation domain.
     *
     * @param ItemInterface        $menu              will be modified and returned
     * @param string               $name              the source of the final label
     * @param string|null          $translationDomain for label translation
     * @param array<string, mixed> $options           menu item options
     *
     * @phpstan-template T of object
     * @phpstan-param AdminInterface<T> $admin
     */
    private function createMenuItem(
        AdminInterface $admin,
        ItemInterface $menu,
        string $name,
        ?string $translationDomain = null,
        array $options = []
    ): ItemInterface {
        $options = array_merge([
            'extras' => [
                'translation_domain' => $translationDomain,
            ],
        ], $options);

        return $menu->addChild(
            $admin->getLabelTranslatorStrategy()->getLabel(
                $name,
                'breadcrumb',
                'link'
            ),
            $options
        );
    }
}
