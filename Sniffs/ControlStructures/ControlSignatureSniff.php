<?php

class Symfony2_Sniffs_ControlStructures_ControlSignatureSniff extends Squiz_Sniffs_ControlStructures_ControlSignatureSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * These tokens are allowed one empty line if they
     * occur after a control statement; otherwise control
     * statements shall not have any empty lines after
     * the opening brace.
     *
     * @var array
     */
    private $controlNewlineExceptions = array(
        T_IF,
        T_WHILE,
        T_TRY,
        T_THROW,
        T_FOR,
        T_FOREACH,
        T_ELSE,
        T_ELSEIF,
        T_RETURN,
        T_CONTINUE,
        T_BREAK,
        T_COMMENT,
        T_DOC_COMMENT,
        T_DOC_COMMENT_OPEN_TAG,
        T_SWITCH
    );

    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Single space after the keyword.
        $found = 1;
        if ($tokens[($stackPtr + 1)]['code'] !== T_WHITESPACE) {
            $found = 0;
        } else if ($tokens[($stackPtr + 1)]['content'] !== ' ') {
            if (strpos($tokens[($stackPtr + 1)]['content'], $phpcsFile->eolChar) !== false) {
                $found = 'newline';
            } else {
                $found = strlen($tokens[($stackPtr + 1)]['content']);
            }
        }

        if ($found !== 1) {
            $error = 'Expected 1 space after %s keyword; %s found';
            $data  = array(
                strtoupper($tokens[$stackPtr]['content']),
                $found,
            );

            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterKeyword', $data);
            if ($fix === true) {
                if ($found === 0) {
                    $phpcsFile->fixer->addContent($stackPtr, ' ');
                } else {
                    $phpcsFile->fixer->replaceToken(($stackPtr + 1), ' ');
                }
            }
        }

        // Single space after closing parenthesis.
        if (isset($tokens[$stackPtr]['parenthesis_closer']) === true
            && isset($tokens[$stackPtr]['scope_opener']) === true
        ) {
            $closer  = $tokens[$stackPtr]['parenthesis_closer'];
            $opener  = $tokens[$stackPtr]['scope_opener'];
            $content = $phpcsFile->getTokensAsString(($closer + 1), ($opener - $closer - 1));

            if ($content !== ' ') {
                $error = 'Expected 1 space after closing parenthesis; found "%s"';
                $data  = array(str_replace($phpcsFile->eolChar, '\n', $content));
                $fix   = $phpcsFile->addFixableError($error, $closer, 'SpaceAfterCloseParenthesis', $data);
                if ($fix === true) {
                    if ($closer === ($opener - 1)) {
                        $phpcsFile->fixer->addContent($closer, ' ');
                    } else {
                        $phpcsFile->fixer->beginChangeset();
                        $phpcsFile->fixer->addContent($closer, ' {');
                        $phpcsFile->fixer->replaceToken($opener, '');

                        if ($tokens[$opener]['line'] !== $tokens[$closer]['line']) {
                            $next = $phpcsFile->findNext(T_WHITESPACE, ($opener + 1), null, true);
                            if ($tokens[$next]['line'] !== $tokens[$opener]['line']) {
                                for ($i = ($opener + 1); $i < $next; $i++) {
                                    $phpcsFile->fixer->replaceToken($i, '');
                                }
                            }
                        }

                        $phpcsFile->fixer->endChangeset();
                    }
                }
            }//end if
        }//end if

        // Single newline after opening brace.
        if (isset($tokens[$stackPtr]['scope_opener']) === true) {
            $opener = $tokens[$stackPtr]['scope_opener'];
            $next   = $phpcsFile->findNext(T_WHITESPACE, ($opener + 1), null, true);
            $found  = ($tokens[$next]['line'] - $tokens[$opener]['line']);
            $isException = in_array($tokens[$next]['code'], $this->controlNewlineExceptions);
            if (($found !== 1 && !$isException) || $found > 2) {

                // Change error according to type of next statement.
                if ($isException === true) {
                    $error = 'Expected no more than 1 empty line after opening brace; %s found';
                    $data = array($found - 1);
                    $next = $next - 2;
                } else {
                    $error = 'Expected 1 newline after opening brace; %s found';
                    $data  = array($found);
                }

                $fix = $phpcsFile->addFixableError($error, $opener, 'NewlineAfterOpenBrace', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->beginChangeset();
                    for ($i = ($opener + 1); $i < $next; $i++) {
                        if ($found > 0 && $tokens[$i]['line'] === $tokens[$next]['line']) {
                            break;
                        }

                        $phpcsFile->fixer->replaceToken($i, '');
                    }

                    $phpcsFile->fixer->addContent($opener, $phpcsFile->eolChar);
                    $phpcsFile->fixer->endChangeset();
                }
            }
        } else if ($tokens[$stackPtr]['code'] === T_WHILE) {
            // Zero spaces after parenthesis closer.
            $closer = $tokens[$stackPtr]['parenthesis_closer'];
            $found  = 0;
            if ($tokens[($closer + 1)]['code'] === T_WHITESPACE) {
                if (strpos($tokens[($closer + 1)]['content'], $phpcsFile->eolChar) !== false) {
                    $found = 'newline';
                } else {
                    $found = strlen($tokens[($closer + 1)]['content']);
                }
            }

            if ($found !== 0) {
                $error = 'Expected 0 spaces before semicolon; %s found';
                $data  = array($found);
                $fix   = $phpcsFile->addFixableError($error, $closer, 'SpaceBeforeSemicolon', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->replaceToken(($closer + 1), '');
                }
            }
        }//end if

        // Only want to check multi-keyword structures from here on.
        if ($tokens[$stackPtr]['code'] === T_TRY
            || $tokens[$stackPtr]['code'] === T_DO
        ) {
            $closer = $tokens[$stackPtr]['scope_closer'];
        } else if ($tokens[$stackPtr]['code'] === T_ELSE
            || $tokens[$stackPtr]['code'] === T_ELSEIF
        ) {
            $closer = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, ($stackPtr - 1), null, true);
            if ($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET) {
                return;
            }
        } else {
            return;
        }

        // Single space after closing brace.
        $found = 1;
        if ($tokens[($closer + 1)]['code'] !== T_WHITESPACE) {
            $found = 0;
        } else if ($tokens[($closer + 1)]['content'] !== ' ') {
            if (strpos($tokens[($closer + 1)]['content'], $phpcsFile->eolChar) !== false) {
                $found = 'newline';
            } else {
                $found = strlen($tokens[($closer + 1)]['content']);
            }
        }

        if ($found !== 1) {
            $error = 'Expected 1 space after closing brace; %s found';
            $data  = array($found);
            $fix   = $phpcsFile->addFixableError($error, $closer, 'SpaceAfterCloseBrace', $data);
            if ($fix === true) {
                if ($found === 0) {
                    $phpcsFile->fixer->addContent($closer, ' ');
                } else {
                    $phpcsFile->fixer->replaceToken(($closer + 1), ' ');
                }
            }
        }
    }
}
