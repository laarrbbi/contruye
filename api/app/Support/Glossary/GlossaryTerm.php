<?php

namespace App\Support\Glossary;

use League\CommonMark\Node\Inline\AbstractInline;

/** A tax or an institution the text names, with its entry in the glossary. */
final class GlossaryTerm extends AbstractInline
{
    public function __construct(public readonly string $key, public readonly string $literal)
    {
        parent::__construct();
    }
}
