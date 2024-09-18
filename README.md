# Custom PHP CS Fixer rules
A few custom rules for use with [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).
Currently, all of them are alternate versions of built-in rules:

* `WHFixer/empty_body`: An alternate version of `single_line_empty_body` which also allows the curly braces to be on the subsequent line (with matching indentation).
* `WHFixer/no_trailing_comma_in_multiline`: An alternate version of `trailing_comma_in_multiline` which requires that multi-line lists and arrays __not__ have a trailing comma.
* `WHFixer/visibility_required`: An alternate version of `visibility_required` which instead forces `static` to be declared _before_ the visibility (not after).
