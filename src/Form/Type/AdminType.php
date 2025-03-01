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

namespace Sonata\AdminBundle\Form\Type;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\DataTransformer\ArrayToModelTransformer;
use Sonata\AdminBundle\Manipulator\ObjectManipulator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * @psalm-suppress MissingTemplateParam https://github.com/phpstan/phpstan-symfony/issues/320
 */
final class AdminType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $admin = clone $this->getAdmin($options);

        if ($admin->hasParentFieldDescription()) {
            $admin->getParentFieldDescription()->setAssociationAdmin($admin);
        }

        if (true === $options['delete'] && $admin->hasAccess('delete')) {
            $deleteOptions = $options['delete_options'];
            if (!\array_key_exists('translation_domain', $deleteOptions['type_options'])) {
                $deleteOptions['type_options']['translation_domain'] = $admin->getTranslationDomain();
            }

            $builder->add('_delete', $deleteOptions['type'], $deleteOptions['type_options']);
        }

        // hack to make sure the subject is correctly set
        // https://github.com/sonata-project/SonataAdminBundle/pull/2076
        if (null === $builder->getData()) {
            $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
                ->disableMagicCall()
                ->enableExceptionOnInvalidIndex()
                ->getPropertyAccessor();

            if ($admin->hasParentFieldDescription()) {
                $parentFieldDescription = $admin->getParentFieldDescription();
                $parentAdmin = $parentFieldDescription->getAdmin();

                if ($parentAdmin->hasSubject() && isset($options['property_path'])) {
                    // this check is to work around duplication issue in property path
                    // https://github.com/sonata-project/SonataAdminBundle/issues/4425
                    if ($this->getFieldDescription($options)->getFieldName() === $options['property_path']) {
                        $path = $options['property_path'];
                    } else {
                        $path = $this->getFieldDescription($options)->getFieldName().$options['property_path'];
                    }

                    $parentPath = implode(
                        '',
                        array_map(
                            static fn (array $associationMapping): string => \sprintf('%s.', $associationMapping['fieldName']),
                            $this->getFieldDescription($options)->getParentAssociationMappings()
                        )
                    );
                    $parentSubject = $parentAdmin->getSubject();

                    try {
                        $subject = $propertyAccessor->getValue($parentSubject, $parentPath.$path);
                    } catch (NoSuchIndexException) {
                        // no object here, we create a new one
                        $subject = $admin->getNewInstance();

                        if (true === $options['collection_by_reference']) {
                            $subject = ObjectManipulator::addInstance($parentSubject, $subject, $parentFieldDescription);
                        }
                        $subject = ObjectManipulator::setObject($subject, $parentSubject, $parentFieldDescription);
                    }
                }
            }

            $builder->setData($subject ?? $admin->getNewInstance());
        }

        $admin->setSubject($builder->getData());

        $admin->defineFormBuilder($builder);

        $builder->addModelTransformer(new ArrayToModelTransformer($admin->getModelManager(), $admin->getClass()));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['btn_add'] = $options['btn_add'];
        $view->vars['btn_list'] = $options['btn_list'];
        $view->vars['btn_delete'] = $options['btn_delete'];

        // NEXT_MAJOR: Remove the btn_catalogue usage.
        $view->vars['btn_translation_domain'] =
            'SonataAdminBundle' !== $options['btn_translation_domain']
                ? $options['btn_translation_domain']
                : $options['btn_catalogue'];
        $view->vars['btn_catalogue'] = $options['btn_catalogue'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'delete' => static fn (Options $options): bool => false !== $options['btn_delete'],
            'delete_options' => [
                'type' => CheckboxType::class,
                'type_options' => [
                    'required' => false,
                    'mapped' => false,
                ],
            ],
            'auto_initialize' => false,
            'btn_add' => 'link_add',
            'btn_list' => 'link_list',
            'btn_delete' => 'link_delete',
            'btn_catalogue' => 'SonataAdminBundle', // NEXT_MAJOR: Remove this option.
            'btn_translation_domain' => 'SonataAdminBundle',
            'collection_by_reference' => true,
        ]);

        $resolver->setDeprecated(
            'btn_catalogue',
            'sonata-project/admin-bundle',
            '4.9',
            static function (Options $options, mixed $value): string {
                if ('SonataAdminBundle' !== $value) {
                    return 'Passing a value to option "btn_catalogue" is deprecated! Use "btn_translation_domain" instead!';
                }

                return '';
            },
        ); // NEXT_MAJOR: Remove this deprecation notice.
    }

    public function getBlockPrefix(): string
    {
        return 'sonata_type_admin';
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \RuntimeException
     */
    private function getFieldDescription(array $options): FieldDescriptionInterface
    {
        if (!isset($options['sonata_field_description'])) {
            throw new \RuntimeException('Please provide a valid `sonata_field_description` option');
        }

        return $options['sonata_field_description'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return AdminInterface<object>
     */
    private function getAdmin(array $options): AdminInterface
    {
        return $this->getFieldDescription($options)->getAssociationAdmin();
    }
}
