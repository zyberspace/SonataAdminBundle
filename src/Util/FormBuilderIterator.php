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

namespace Sonata\AdminBundle\Util;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * @phpstan-extends \RecursiveArrayIterator<string, FormBuilderInterface>
 */
final class FormBuilderIterator extends \RecursiveArrayIterator
{
    private string $prefix;

    /**
     * @var \ArrayIterator<string|int, string|int>
     */
    private \ArrayIterator $iterator;

    public function __construct(
        private FormBuilderInterface $formBuilder,
        ?string $prefix = null
    ) {
        parent::__construct();
        $this->prefix = $prefix ?? $formBuilder->getName();
        $this->iterator = new \ArrayIterator(self::getKeys($formBuilder));
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function key(): string
    {
        $name = $this->iterator->current();

        return \sprintf('%s_%s', $this->prefix, $name);
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function current(): FormBuilderInterface
    {
        return $this->formBuilder->get((string) $this->iterator->current());
    }

    public function getChildren(): self
    {
        return new self($this->current(), $this->key());
    }

    public function hasChildren(): bool
    {
        return \count(self::getKeys($this->current())) > 0;
    }

    /**
     * @return array<string|int, string|int>
     */
    private static function getKeys(FormBuilderInterface $formBuilder): array
    {
        return array_keys($formBuilder->all());
    }
}
