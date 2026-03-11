<?php

/**
 * @file
 */

declare(strict_types=1);

use PhpCsFixer\Fixer\Basic\EncodingFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTypesFixer;
use PhpCsFixer\Fixer\Casing\ConstantCaseFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocScalarFixer;
use PhpCsFixer\Fixer\PhpTag\NoClosingTagFixer;
use PhpCsFixer\Fixer\Basic\BracesPositionFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\PhpTag\FullOpeningTagFixer;
use PhpCsFixer\Fixer\Whitespace\LineEndingFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use PhpCsFixer\Fixer\CastNotation\CastSpacesFixer;
use PhpCsFixer\Fixer\ControlStructure\ElseifFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Casing\LowercaseKeywordsFixer;
use PhpCsFixer\Fixer\Casing\MagicMethodCasingFixer;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Casing\MagicConstantCasingFixer;
use PhpCsFixer\Fixer\CastNotation\LowercaseCastFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer;
use PhpCsFixer\Fixer\Operator\OperatorLinebreakFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer;
use PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\Whitespace\IndentationTypeFixer;
use PhpCsFixer\Fixer\Casing\NativeFunctionCasingFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedTraitsFixer;
use PhpCsFixer\Fixer\Operator\NewWithParenthesesFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocVarWithoutNameFixer;
use PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer;
use PhpCsFixer\Fixer\CastNotation\ShortScalarCastFixer;
use PhpCsFixer\Fixer\Operator\UnaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\AlignMultilineCommentFixer;
use PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer;
use PhpCsFixer\Fixer\ArrayNotation\TrimArraySpacesFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer;
use PhpCsFixer\Fixer\Import\SingleLineAfterImportsFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Operator\StandardizeNotEqualsFixer;
use PhpCsFixer\Fixer\Semicolon\SpaceAfterSemicolonFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUselessElseFixer;
use PhpCsFixer\Fixer\Operator\TernaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Casing\LowercaseStaticReferenceFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedInterfacesFixer;
use PhpCsFixer\Fixer\Import\SingleImportPerStatementFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\Whitespace\NoSpacesAroundOffsetFixer;
use PhpCsFixer\Fixer\Whitespace\NoTrailingWhitespaceFixer;
use PhpCsFixer\Fixer\Whitespace\SingleBlankLineAtEofFixer;
use PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfStaticAccessorFixer;
use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\ControlStructure\SwitchCaseSpaceFixer;
use PhpCsFixer\Fixer\Whitespace\TypeDeclarationSpacesFixer;
use PhpCsFixer\Fixer\Basic\NoTrailingCommaInSinglelineFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSingleLineVarSpacingFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\Whitespace\NoWhitespaceInBlankLineFixer;
use PhpCsFixer\Fixer\Whitespace\SpacesInsideParenthesesFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\ControlStructure\NoSuperfluousElseifFixer;
use PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureBracesFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DeclareEqualNormalizeFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBetweenImportGroupsFixer;
use PhpCsFixer\Fixer\ArrayNotation\WhitespaceAfterCommaInArrayFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\NamespaceNotation\BlankLineAfterNamespaceFixer;
use PhpCsFixer\Fixer\FunctionNotation\NoSpacesAfterFunctionNameFixer;
use PhpCsFixer\Fixer\ClassNotation\SingleTraitInsertPerStatementFixer;
use PhpCsFixer\Fixer\ControlStructure\SwitchCaseSemicolonToColonFixer;
use PhpCsFixer\Fixer\NamespaceNotation\BlankLinesBeforeNamespaceFixer;
use PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceBeforeCommaInArrayFixer;
use PhpCsFixer\Fixer\ClassNotation\SingleClassElementPerStatementFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer;
use PhpCsFixer\Fixer\Semicolon\NoSinglelineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;

return ECSConfig::configure()
  ->withPaths([
    __DIR__ . '/src',
  ])
  ->withRootFiles()
  ->withSkip([
    __DIR__ . '/vendor',
    __DIR__ . '/web/core',
    __DIR__ . '/web/modules/contrib',
    __DIR__ . '/web/themes/contrib',
  ])
  ->withFileExtensions([
    'php',
    'module',
    'inc',
    'install',
    'test',
    'profile',
    'theme',
  ])

  // 2-space indentation (Drupal standard)
  ->withSpacing(indentation: '  ', lineEnding: "\n")

  // Rules without configuration.
  ->withRules([
    // Encoding.
    EncodingFixer::class,
    FullOpeningTagFixer::class,
    NoClosingTagFixer::class,

    // Indentation & Whitespace.
    IndentationTypeFixer::class,
    LineEndingFixer::class,
    ArrayIndentationFixer::class,
    MethodChainingIndentationFixer::class,
    NoTrailingWhitespaceFixer::class,
    NoWhitespaceInBlankLineFixer::class,
    SingleBlankLineAtEofFixer::class,
    StatementIndentationFixer::class,
    NoSpacesAroundOffsetFixer::class,

    // Casing.
    LowercaseKeywordsFixer::class,
    LowercaseStaticReferenceFixer::class,
    LowercaseCastFixer::class,
    ShortScalarCastFixer::class,
    MagicConstantCasingFixer::class,
    MagicMethodCasingFixer::class,
    NativeFunctionCasingFixer::class,

    // Arrays.
    TrimArraySpacesFixer::class,
    WhitespaceAfterCommaInArrayFixer::class,
    NoWhitespaceBeforeCommaInArrayFixer::class,
    NoTrailingCommaInSinglelineFixer::class,

    // Control Structures.
    ControlStructureBracesFixer::class,
    ElseifFixer::class,
    NoSuperfluousElseifFixer::class,
    NoUselessElseFixer::class,
    SwitchCaseSemicolonToColonFixer::class,
    SwitchCaseSpaceFixer::class,

    // Classes.
    SingleClassElementPerStatementFixer::class,
    SingleTraitInsertPerStatementFixer::class,
    SelfAccessorFixer::class,
    SelfStaticAccessorFixer::class,
    OrderedInterfacesFixer::class,
    OrderedTraitsFixer::class,

    // Functions.
    NoSpacesAfterFunctionNameFixer::class,

    // Imports.
    NoUnusedImportsFixer::class,
    SingleImportPerStatementFixer::class,
    SingleLineAfterImportsFixer::class,
    BlankLineAfterNamespaceFixer::class,
    BlankLineAfterOpeningTagFixer::class,
    BlankLineBetweenImportGroupsFixer::class,

    // Operators.
    BinaryOperatorSpacesFixer::class,
    StandardizeNotEqualsFixer::class,
    TernaryOperatorSpacesFixer::class,

    // PHPDoc.
    PhpdocIndentFixer::class,
    PhpdocTrimFixer::class,
    PhpdocSeparationFixer::class,
    PhpdocScalarFixer::class,
    PhpdocTypesFixer::class,
    PhpdocSingleLineVarSpacingFixer::class,
    PhpdocTrimConsecutiveBlankLineSeparationFixer::class,
    PhpdocNoEmptyReturnFixer::class,
    PhpdocOrderFixer::class,
    PhpdocVarWithoutNameFixer::class,
    NoEmptyPhpdocFixer::class,

    // Semicolons.
    NoEmptyStatementFixer::class,
    NoSinglelineWhitespaceBeforeSemicolonsFixer::class,

    // Returns.
    NoUselessReturnFixer::class,

    // Strict (optional - remove if not wanted)
    DeclareStrictTypesFixer::class,
    StrictComparisonFixer::class,
  ])

  // Rules with configuration.
  ->withConfiguredRule(BracesPositionFixer::class, [
    'classes_opening_brace' => 'same_line',
    'functions_opening_brace' => 'same_line',
    'anonymous_classes_opening_brace' => 'same_line',
    'anonymous_functions_opening_brace' => 'same_line',
    'control_structures_opening_brace' => 'same_line',
    'allow_single_line_empty_anonymous_classes' => FALSE,
    'allow_single_line_anonymous_functions' => FALSE,
  ])
  ->withConfiguredRule(ControlStructureContinuationPositionFixer::class, [
    'position' => 'same_line',
  ])
  ->withConfiguredRule(ArraySyntaxFixer::class, [
    'syntax' => 'short',
  ])
  ->withConfiguredRule(TrailingCommaInMultilineFixer::class, [
    'elements' => ['arrays', 'arguments', 'parameters'],
  ])
  ->withConfiguredRule(CastSpacesFixer::class, [
    'space' => 'single',
  ])
  ->withConfiguredRule(ConcatSpaceFixer::class, [
    'spacing' => 'one',
  ])
  ->withConfiguredRule(ConstantCaseFixer::class, [
    'case' => 'upper',
  ])
  ->withConfiguredRule(VisibilityRequiredFixer::class, [
    'elements' => ['method', 'property', 'const'],
  ])
  // Fix for CloseBraceAfterBody, FunctionSpacing.AfterLast, FunctionSpacing.BeforeFirst.
  ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
    'elements' => [
      'const' => 'one',
      'method' => 'one',
      'property' => 'one',
      'trait_import' => 'none',
      'case' => 'none',
    ],
  ])
  ->withConfiguredRule(ClassDefinitionFixer::class, [
    'single_line' => FALSE,
    'space_before_parenthesis' => TRUE,
    'inline_constructor_arguments' => FALSE,
  ])
  ->withConfiguredRule(OrderedClassElementsFixer::class, [
    'order' => [
      'use_trait',
      'case',
      'constant',
      'constant_public',
      'constant_protected',
      'constant_private',
      'property_public',
      'property_protected',
      'property_private',
      'construct',
      'destruct',
      'magic',
      'phpunit',
      'method_abstract',
      'method_public_static',
      'method_public',
      'method_protected_static',
      'method_protected',
      'method_private_static',
      'method_private',
    ],
    'sort_algorithm' => 'none',
  ])
  ->withConfiguredRule(NewWithParenthesesFixer::class, [
    'anonymous_class' => TRUE,
    'named_class' => TRUE,
  ])
  ->withConfiguredRule(FunctionDeclarationFixer::class, [
    'closure_function_spacing' => 'one',
    'closure_fn_spacing' => 'one',
  ])
  ->withConfiguredRule(MethodArgumentSpaceFixer::class, [
    'on_multiline' => 'ensure_fully_multiline',
    'keep_multiple_spaces_after_comma' => FALSE,
  ])
  ->withConfiguredRule(ReturnTypeDeclarationFixer::class, [
    'space_before' => 'none',
  ])
  ->withConfiguredRule(OrderedImportsFixer::class, [
    'sort_algorithm' => 'length',
    'imports_order' => ['class', 'function', 'const'],
  ])
  ->withConfiguredRule(BlankLinesBeforeNamespaceFixer::class, [
    'min_line_breaks' => 2,
    'max_line_breaks' => 2,
  ])
  // Fix for NewlineAfterCloseBrace - ensure proper blank lines.
  ->withConfiguredRule(NoExtraBlankLinesFixer::class, [
    'tokens' => [
      'extra',
      'throw',
      'use',
    ],
  ])
  ->withConfiguredRule(SpacesInsideParenthesesFixer::class, [
    'space' => 'none',
  ])
  ->withConfiguredRule(TypeDeclarationSpacesFixer::class, [
    'elements' => ['function', 'property'],
  ])
  ->withConfiguredRule(UnaryOperatorSpacesFixer::class, [
    'only_dec_inc' => TRUE,
  ])
  ->withConfiguredRule(OperatorLinebreakFixer::class, [
    'only_booleans' => TRUE,
    'position' => 'beginning',
  ])
  ->withConfiguredRule(BlankLineBeforeStatementFixer::class, [
    'statements' => ['return', 'throw', 'try'],
  ])
  ->withConfiguredRule(SpaceAfterSemicolonFixer::class, [
    'remove_in_empty_for_expressions' => TRUE,
  ])
  ->withConfiguredRule(SingleQuoteFixer::class, [
    'strings_containing_single_quote_chars' => FALSE,
  ])
  ->withConfiguredRule(DeclareEqualNormalizeFixer::class, [
    'space' => 'none',
  ])
  ->withConfiguredRule(AlignMultilineCommentFixer::class, [
    'comment_type' => 'phpdocs_only',
  ])
  ->withConfiguredRule(PhpdocTypesOrderFixer::class, [
    'null_adjustment' => 'always_last',
    'sort_algorithm' => 'none',
  ])
  ->withConfiguredRule(NoSuperfluousPhpdocTagsFixer::class, [
    'allow_mixed' => TRUE,
    'remove_inheritdoc' => FALSE,
  ])
  ->withConfiguredRule(PhpdocLineSpanFixer::class, [
    'const' => 'single',
    'property' => 'single',
    'method' => 'multi',
  ]);
