<?php

abstract class ScopeKeywords
{
    public $ok = 1;
    private static $alsoOk = 2;

    // @mago-expect lint:generic/scope-keyword-spacing
    public  $tooMuch = 1;

    // @mago-expect lint:generic/scope-keyword-spacing
    protected    $muchMore = 1;

    // @mago-expect lint:generic/scope-keyword-spacing
    public
    function newlineAfterKeyword() {}

    // @mago-expect lint:generic/scope-keyword-spacing
    public static  function twoModifiers() {}

    // @mago-expect lint:generic/scope-keyword-spacing
    final  public function finalModifier() {}

    // @mago-expect lint:generic/scope-keyword-spacing
    abstract  protected function abstractModifier();

    // @mago-expect lint:generic/scope-keyword-spacing
    private  readonly string $readonlyProperty;

    // `static` as a return type or a call target is not a modifier.
    public function factory(): static
    {
        $a = new static;
        $b = static::create();
        $c = $a instanceof static || $b instanceof static;

        return $a;
    }

    // Constructor promotion.
    public function __construct(
        // @mago-expect lint:generic/scope-keyword-spacing
        public  string $promoted,
        private readonly int $fine,
    ) {}
}
