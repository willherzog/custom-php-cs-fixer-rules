<?php

namespace WHPHP\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\{ConfigurableFixerInterface,ConfigurableFixerTrait};
use PhpCsFixer\FixerConfiguration\AllowedValueSubset;
use PhpCsFixer\FixerConfiguration\{FixerConfigurationResolver,FixerConfigurationResolverInterface};
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\{FixerDefinition,FixerDefinitionInterface};
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\{Token,Tokens,TokensAnalyzer};

/**
 * An alternate version of {@link PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer}
 * which instead forces `static` to be declared before the visibility.
 *
 * It also has non-conditional support for `readonly` as this package itself requires PHP 8.2+.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author Will Herzog <willherzog@gmail.com>
 */
class VisibilityRequiredFixer extends AbstractFixer implements ConfigurableFixerInterface
{
	use ConfigurableFixerTrait;

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Visibility MUST be declared on all properties and methods; `abstract`, `final` and `static` MUST be declared before the visibility; `readonly` MUST be declared after the visibility.',
			[
				new CodeSample(
					'<?php
class Sample
{
	var $a;
	protected static $var_foo2;

	function A()
	{
	}
}
'
				),
				new CodeSample(
					'<?php
class Sample
{
	const SAMPLE = 1;
}
',
					['elements' => ['const']]
				)
			]
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Must run before {@link PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer}.
	 */
	public function getPriority(): int
	{
		return 56;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound(Token::getClassyTokenKinds());
	}

	protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
	{
		return new FixerConfigurationResolver([
			(new FixerOptionBuilder('elements', 'The structural elements to fix.'))
				->setAllowedTypes(['string[]'])
				->setAllowedValues([new AllowedValueSubset(['property', 'method', 'const'])])
				->setDefault(['property', 'method', 'const'])
				->getOption()
		]);
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$tokensAnalyzer = new TokensAnalyzer($tokens);
		$propertyTypeDeclarationKinds = [T_STRING, T_NS_SEPARATOR, CT::T_NULLABLE_TYPE, CT::T_ARRAY_TYPEHINT, CT::T_TYPE_ALTERNATION, CT::T_TYPE_INTERSECTION, CT::T_DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN, CT::T_DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE, T_READONLY];

		$expectedKindsGeneric = [T_ABSTRACT, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_STATIC, T_VAR];
		$expectedKindsPropertyKinds = [...$expectedKindsGeneric, ...$propertyTypeDeclarationKinds];

		foreach( \array_reverse($tokensAnalyzer->getClassyElements(), true) as $index => $element ) {
			if( !\in_array($element['type'], $this->configuration['elements'], true) ) {
				continue;
			}

			$abstractFinalIndex = $visibilityIndex = $staticIndex = $typeIndex = $readOnlyIndex = null;
			$prevIndex = $tokens->getPrevMeaningfulToken($index);
			$expectedKinds = $element['type'] === 'property' ? $expectedKindsPropertyKinds : $expectedKindsGeneric;

			while( $tokens[$prevIndex]->isGivenKind($expectedKinds) ) {
				if( $tokens[$prevIndex]->isGivenKind([T_ABSTRACT, T_FINAL]) ) {
					$abstractFinalIndex = $prevIndex;
				} elseif( $tokens[$prevIndex]->isGivenKind(T_STATIC) ) {
					$staticIndex = $prevIndex;
				} elseif( $tokens[$prevIndex]->isGivenKind(T_READONLY) ) {
					$readOnlyIndex = $prevIndex;
				} elseif( $tokens[$prevIndex]->isGivenKind($propertyTypeDeclarationKinds) ) {
					$typeIndex = $prevIndex;
				} else {
					$visibilityIndex = $prevIndex;
				}

				$prevIndex = $tokens->getPrevMeaningfulToken($prevIndex);
			}

			if( $typeIndex !== null ) {
				$index = $typeIndex;
			}

			if( $tokens[$prevIndex]->equals(',') ) {
				continue;
			}

			if( $readOnlyIndex !== null ) {
				if( $this->isKeywordPlacedProperly($tokens, $readOnlyIndex, $index) ) {
					$index = $readOnlyIndex;
				} else {
					$this->moveTokenAndEnsureSingleSpaceFollows($tokens, $readOnlyIndex, $index);
				}
			}

			if( $visibilityIndex === null ) {
				$tokens->insertAt($index, [new Token([T_PUBLIC, 'public']), new Token([T_WHITESPACE, ' '])]);
			} else {
				if( $tokens[$visibilityIndex]->isGivenKind(T_VAR) ) {
					$tokens[$visibilityIndex] = new Token([T_PUBLIC, 'public']);
				}
				if( $this->isKeywordPlacedProperly($tokens, $visibilityIndex, $index) ) {
					$index = $visibilityIndex;
				} else {
					$this->moveTokenAndEnsureSingleSpaceFollows($tokens, $visibilityIndex, $index);
				}
			}

			if( $staticIndex !== null ) {
				if( $this->isKeywordPlacedProperly($tokens, $staticIndex, $index) ) {
					$index = $staticIndex;
				} else {
					$this->moveTokenAndEnsureSingleSpaceFollows($tokens, $staticIndex, $index);
				}
			}

			if( $abstractFinalIndex === null || $this->isKeywordPlacedProperly($tokens, $abstractFinalIndex, $index) ) {
				continue;
			}

			$this->moveTokenAndEnsureSingleSpaceFollows($tokens, $abstractFinalIndex, $index);
		}
	}

	private function isKeywordPlacedProperly(Tokens $tokens, int $keywordIndex, int $comparedIndex): bool
	{
		return $comparedIndex === $keywordIndex + 2 && $tokens[$keywordIndex + 1]->getContent() === ' ';
	}

	private function moveTokenAndEnsureSingleSpaceFollows(Tokens $tokens, int $fromIndex, int $toIndex): void
	{
		$tokens->insertAt($toIndex, [$tokens[$fromIndex], new Token([T_WHITESPACE, ' '])]);
		$tokens->clearAt($fromIndex);

		if( $tokens[$fromIndex + 1]->isWhitespace() ) {
			$tokens->clearAt($fromIndex + 1);
		}
	}
}
