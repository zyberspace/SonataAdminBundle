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

namespace Sonata\AdminBundle\DependencyInjection\Compiler;

use Sonata\AdminBundle\DependencyInjection\Admin\TaggedAdminInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * This class configures which admins must be considered for global search at `SearchHandler`.
 *
 * @internal
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
final class AdminSearchCompilerPass implements CompilerPassInterface
{
    public const TAG_ATTRIBUTE_TOGGLE_SEARCH = 'global_search';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('sonata.admin.search.handler')) {
            return;
        }

        $adminSearch = [];

        foreach ($container->findTaggedServiceIds(TaggedAdminInterface::ADMIN_TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                $globalSearch = $this->getGlobalSearchValue($attributes, $id);
                if (null === $globalSearch) {
                    continue;
                }

                $adminCode = $attributes['code'] ?? $id;
                $adminSearch[$adminCode] = $globalSearch;
            }
        }

        $searchHandlerDefinition = $container->getDefinition('sonata.admin.search.handler');
        $searchHandlerDefinition->addMethodCall('configureAdminSearch', [$adminSearch]);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws LogicException if the attribute value is not of type boolean
     */
    private function getGlobalSearchValue(array $attributes, string $id): ?bool
    {
        $globalSearch = $attributes[self::TAG_ATTRIBUTE_TOGGLE_SEARCH] ?? null;

        if (null === $globalSearch) {
            return null;
        }

        if (!\is_bool($globalSearch)) {
            throw new LogicException(\sprintf(
                'Attribute "%s" in tag "%s" at service "%s" must be of type boolean, "%s" given.',
                self::TAG_ATTRIBUTE_TOGGLE_SEARCH,
                TaggedAdminInterface::ADMIN_TAG,
                $id,
                \gettype($globalSearch)
            ));
        }

        return $globalSearch;
    }
}
