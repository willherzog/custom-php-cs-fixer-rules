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
use PhpCsFixer\Tokenizer\{Tokens,TokensAnalyzer};

/**
 * An alternate version of {@link PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer}
 * which instead requires that multi-line lists and arrays not have a trailing comma.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author Kuba Werłos <werlos@gmail.com>
 * @author Will Herzog <willherzog@gmail.com>
 */
class NoTrailingCommaInMultilineFixer extends AbstractFixer implements ConfigurableFixerInterface
{
	use ConfigurableFixerTrait;

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Arguments lists, array destructuring lists, arrays that are multi-line, `match`-lines and parameters lists must not have a trailing comma.',
			[
				new CodeSample("<?php\narray(\n\t1,\n\t2,\n);\n"),
				new CodeSample("<?php\nfoo(\n\t1,\n\t2,\n);\n", ['elements' => ['arguments']]),
				new CodeSample("<?php\nfunction foo(\n\t\$x,\n\t\$y,\n)\n{\n}\n", ['elements' => ['parameters']])
			]
		);
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound([T_ARRAY, CT::T_ARRAY_SQUARE_BRACE_OPEN, '(', CT::T_DESTRUCTURING_SQUARE_BRACE_OPEN]);
	}

	protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
	{
		return new FixerConfigurationResolver([
			(new FixerOptionBuilder('elements', 'Where to fix multiline trailing comma.'))
				->setAllowedTypes(['string[]'])
				->setAllowedValues([
					new AllowedValueSubset(['array_destructuring', 'arguments', 'arrays', 'parameters', 'match']),
				])
				->setDefault(['array_destructuring', 'arrays', 'parameters', 'match'])
				->getOption()
		]);
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$configuredElements = $this->configuration['elements'];

		$fixArguments = \in_array('arguments', $configuredElements, true);
		$fixArrays = \in_array('arrays', $configuredElements, true);
		$fixDestructuring = \in_array('array_destructuring', $configuredElements, true);
		$fixMatch = \in_array('match', $configuredElements, true);
		$fixParameters = \in_array('parameters', $configuredElements, true);

		for( $index = $tokens->count() - 1; $index >= 0; --$index ) {
			// array destructing short syntax
			if( $tokens[$index]->isGivenKind(CT::T_DESTRUCTURING_SQUARE_BRACE_OPEN) ) {
				if( $fixDestructuring ) {
					$this->fixBlock($tokens, $index);
				}

				continue;
			}

			// array short syntax
			if( $tokens[$index]->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN) ) {
				if( $fixArrays ) {
					$this->fixBlock($tokens, $index);
				}

				continue;
			}

			if( !$tokens[$index]->equals('(') ) {
				continue;
			}

			$prevIndex = $tokens->getPrevMeaningfulToken($index);

			// array long syntax
			if( $tokens[$prevIndex]->isGivenKind(T_ARRAY) ) {
				if($fixArrays ) {
					$this->fixBlock($tokens, $index);
				}

				continue;
			}

			// array destructing long syntax
			if( $tokens[$prevIndex]->isGivenKind(T_LIST) ) {
				if( $fixDestructuring || $fixArguments ) {
					$this->fixBlock($tokens, $index);
				}

				continue;
			}

			if( $fixMatch && $tokens[$prevIndex]->isGivenKind(T_MATCH) ) {
				$this->fixMatch($tokens, $index);

				continue;
			}

			$prevPrevIndex = $tokens->getPrevMeaningfulToken($prevIndex);

			if(
				$fixArguments
				&& $tokens[$prevIndex]->equalsAny([']', [T_CLASS], [T_STRING], [T_VARIABLE], [T_STATIC], [T_ISSET], [T_UNSET], [T_LIST]])
				&& !$tokens[$prevPrevIndex]->isGivenKind(T_FUNCTION)
			) {
				$this->fixBlock($tokens, $index);

				continue;
			}

			if(
				$fixParameters
				&& (
					$tokens[$prevIndex]->isGivenKind(T_STRING)
					&& $tokens[$prevPrevIndex]->isGivenKind(T_FUNCTION)
					|| $tokens[$prevIndex]->isGivenKind([T_FN, T_FUNCTION])
				)
			) {
				$this->fixBlock($tokens, $index);
			}
		}
	}

	private function fixBlock(Tokens $tokens, int $startIndex): void
	{
		$tokensAnalyzer = new TokensAnalyzer($tokens);

		if( !$tokensAnalyzer->isBlockMultiline($tokens, $startIndex) ) {
			return;
		}

		$blockType = Tokens::detectBlockType($tokens[$startIndex]);
		$endIndex = $tokens->findBlockEnd($blockType['type'], $startIndex);

		$commaIndex = $tokens->getPrevMeaningfulToken($endIndex);

		if( !$tokens->isPartialCodeMultiline($commaIndex, $endIndex) ) {
			return;
		}

		if( $tokens[$commaIndex]->equals(',') ) {
			do {
				$tokens->clearTokenAndMergeSurroundingWhitespace($commaIndex);
				$commaIndex = $tokens->getPrevMeaningfulToken($commaIndex);
			} while( $tokens[$commaIndex]->equals(',') );

			$tokens->removeTrailingWhitespace($commaIndex);
		}
	}

	private function fixMatch(Tokens $tokens, int $index): void
	{
		$index = $tokens->getNextTokenOfKind($index, ['{']);
		$closeIndex = $index;
		$isMultiline = false;
		$depth = 1;

		do {
			$closeIndex++;

			if( $tokens[$closeIndex]->equals('{') ) {
				$depth++;
			} elseif( $tokens[$closeIndex]->equals('}') ) {
				$depth--;
			} elseif( !$isMultiline && str_contains($tokens[$closeIndex]->getContent(), "\n") ) {
				$isMultiline = true;
			}
		} while( $depth > 0 );

		if( !$isMultiline ) {
			return;
		}

		$commaIndex = $tokens->getPrevMeaningfulToken($closeIndex);

		if( !$tokens->isPartialCodeMultiline($commaIndex, $closeIndex) ) {
			return;
		}

		if( $tokens[$commaIndex]->equals(',') ) {
			do {
				$tokens->clearTokenAndMergeSurroundingWhitespace($commaIndex);
				$commaIndex = $tokens->getPrevMeaningfulToken($commaIndex);
			} while( $tokens[$commaIndex]->equals(',') );
		}
	}
}
