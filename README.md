# Custom PHP CS Fixer rules
A few custom rules for use with [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).
Currently, all of them are alternate versions of built-in rules:

* `WHPHP\Fixer\EmptyBodyFixer`: An alternate version of `PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer` which also allows the curly braces to be on the subsequent line (with matching indentation).
* `WHPHP\Fixer\NoTrailingCommaInMultilineFixer`: An alternate version of `PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer` which requires that multi-line lists and arrays __not__ have a trailing comma.
* `WHPHP\Fixer\VisibilityRequiredFixer`: An alternate version of `PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer` which instead forces `static` to be declared _before_ the visibility (not after).
