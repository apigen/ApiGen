<?php declare(strict_types=1);

namespace ApiGen\Reflection\Reflection\Trait_;

use ApiGen\Annotation\AnnotationList;
use ApiGen\Reflection\Contract\Reflection\Trait_\TraitPropertyReflectionInterface;
use ApiGen\Reflection\Contract\Reflection\Trait_\TraitReflectionInterface;
use ApiGen\Reflection\Contract\TransformerCollectorAwareInterface;
use ApiGen\Reflection\TransformerCollector;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use Roave\BetterReflection\Reflection\ReflectionProperty;

final class TraitPropertyReflection implements TraitPropertyReflectionInterface, TransformerCollectorAwareInterface
{
    /**
     * @var ReflectionProperty
     */
    private $betterPropertyReflection;

    /**
     * @var DocBlock
     */
    private $docBlock;

    /**
     * @var TransformerCollector
     */
    private $transformerCollector;

    public function __construct(ReflectionProperty $betterPropertyReflection, DocBlock $docBlock)
    {
        $this->betterPropertyReflection = $betterPropertyReflection;
        $this->docBlock = $docBlock;
    }

    public function getNamespaceName(): string
    {
        return $this->betterPropertyReflection->getDeclaringClass()
            ->getNamespaceName();
    }

    public function getDescription(): string
    {
        $description = $this->docBlock->getSummary()
            . AnnotationList::EMPTY_LINE
            . $this->docBlock->getDescription();

        return trim($description);
    }

    public function getDeclaringTrait(): TraitReflectionInterface
    {
        return $this->transformerCollector->transformSingle(
            $this->betterPropertyReflection->getDeclaringClass()
        );
    }

    public function getDeclaringTraitName(): string
    {
        return $this->getDeclaringTrait()
            ->getName();
    }

    public function getStartLine(): int
    {
        // @todo
        return 5;
    }

    public function getEndLine(): int
    {
        // @todo
        return 5;
    }

    public function getName(): string
    {
        return $this->betterPropertyReflection->getName();
    }

    /**
     * @todo what does this mean? better naming?
     */
    public function isDefault(): bool
    {
        return $this->betterPropertyReflection->isDefault();
    }

    public function isStatic(): bool
    {
        return $this->betterPropertyReflection->isStatic();
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->betterPropertyReflection->getDefaultValue();
    }

    /**
     * @return string[]
     */
    public function getTypeHints(): array
    {
        $typeHints = $this->betterPropertyReflection->getDocBlockTypeStrings();
        foreach ($typeHints as $k => $typeHint) {
            $typeHints[$k] = ltrim($typeHint, '\\');
        }

        return $typeHints;
    }

    /**
     * @return Tag[]
     */
    public function getAnnotations(): array
    {
        return $this->docBlock->getTags();
    }

    public function hasAnnotation(string $name): bool
    {
        return $this->docBlock->hasTag($name);
    }

    /**
     * @return mixed[]
     */
    public function getAnnotation(string $name): array
    {
        return $this->docBlock->getTagsByName($name);
    }

    public function isPrivate(): bool
    {
        return $this->betterPropertyReflection->isPrivate();
    }

    public function isProtected(): bool
    {
        return $this->betterPropertyReflection->isProtected();
    }

    public function isPublic(): bool
    {
        return $this->betterPropertyReflection->isPublic();
    }

    public function isDeprecated(): bool
    {
        if ($this->getDeclaringTrait()->isDeprecated()) {
            return true;
        }

        return $this->hasAnnotation(AnnotationList::DEPRECATED);
    }

    public function setTransformerCollector(TransformerCollector $transformerCollector): void
    {
        $this->transformerCollector = $transformerCollector;
    }
}
