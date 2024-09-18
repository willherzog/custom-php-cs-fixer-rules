<?php

namespace WHPHP\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\{FixerDefinition,FixerDefinitionInterface};
use PhpCsFixer\Tokenizer\Analyzer\WhitespacesAnalyzer;
use PhpCsFixer\Tokenizer\{Token,Tokens};

/**
 * An alternate version of {@link PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer}
 * which also allows the curly braces to be on the subsequent line.
 *
 * It also has non-conditional support for enumerators as this package itself requires PHP 8.2+.
 *
 * @author Will Herzog <willherzog@gmail.com>
 */
class EmptyBodyFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Empty body of class, interface, trait, enum or function must be abbreviated as `{}` and separated by either a single space or a single new-line character.',
			[new CodeSample('<?php function foo(
	int $x
) {
}
')],
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Must run after {@link PhpCsFixer\Fixer\Basic\BracesPositionFixer}, {@link PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer}, {@link PhpCsFixer\Fixer\Basic\CurlyBracesPositionFixer} and {@link PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer}.
	 */
	public function getPriority(): int
	{
		return -19;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound([T_INTERFACE, T_CLASS, T_FUNCTION, T_TRAIT, T_ENUM]);
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		for( $index = $tokens->count() - 1; $index > 0; $index-- ) {
			if( !$tokens[$index]->isGivenKind([...Token::getClassyTokenKinds(), T_FUNCTION]) ) {
				continue;
			}

			$openBraceIndex = $tokens->getNextTokenOfKind($index, ['{', ';']);

			if( !$tokens[$openBraceIndex]->equals('{') ) {
				continue;
			}

			$closeBraceIndex = $tokens->getNextNonWhitespace($openBraceIndex);

			if( !$tokens[$closeBraceIndex]->equals('}') ) {
				continue;
			}

			$tokens->ensureWhitespaceAtIndex($openBraceIndex + 1, 0, '');

			$beforeOpenBraceIndex = $tokens->getPrevNonWhitespace($openBraceIndex);

			if( !$tokens[$beforeOpenBraceIndex]->isGivenKind([T_COMMENT, T_DOC_COMMENT]) ) {
				$beforeOpenBraceIndex = $openBraceIndex - 1;

				if(
					$tokens[$beforeOpenBraceIndex]->isWhitespace()
					&& preg_match($tokens[$beforeOpenBraceIndex]->getContent(), '/\v/') !== false
				) {
					$lineEnding = $this->whitespacesConfig->getLineEnding();
					$indent = WhitespacesAnalyzer::detectIndent($tokens, $index);

					$tokens->ensureWhitespaceAtIndex($beforeOpenBraceIndex, 1, $lineEnding . $indent);
				} else {
					$tokens->ensureWhitespaceAtIndex($beforeOpenBraceIndex, 1, ' ');
				}
			}
		}
	}
}
