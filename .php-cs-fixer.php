<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/carrefourmarketplace')
    ->name('*.php')
    ->exclude(['node_modules', 'vendor'])
    ->notPath('config.xml')
    ->notPath('*.tpl');

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],
        'trailing_comma_in_multiline' => false,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_blank_lines_after_phpdoc' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],
        'no_whitespace_in_blank_line' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'class_definition' => [
            'single_line' => true,
            'single_item_single_line' => true,
            'multi_line_extends_each_single_line' => true,
            'space_before_parenthesis' => true,
        ],
        'single_space_around_construct' => true,
        'increment_style' => ['style' => 'pre'],
        'operator_linebreak' => [
            'only_booleans' => true,
            'position' => 'beginning',
        ],
        'braces_position' => [
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'allow_single_line_empty_anonymous_classes' => false,
            'allow_single_line_anonymous_functions' => false,
        ],
        'statement_indentation' => true,
        'cast_spaces' => ['space' => 'single'],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
                'break',
                'continue',
                'curly_brace_block',
                'parenthesis_brace_block',
                'square_brace_block',
            ],
        ],
        'control_structure_braces' => true,
        'no_alias_language_construct_call' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
    ])
    ->setFinder($finder);
