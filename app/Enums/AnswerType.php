<?php

namespace App\Enums;

/**
 * How an inspector answers a checklist item. Each type needs its own input
 * in the app and its own checks here, so the list lives in code.
 */
enum AnswerType: string
{
    /** Pick one option from an answer list, e.g. Good / Bad / None. */
    case Choice = 'choice';

    /** A number, with an optional unit and limits, e.g. ODO reading in km. */
    case Number = 'number';

    /** 0 to 100, e.g. tyre wear. */
    case Percentage = 'percentage';

    /** Free text. */
    case Text = 'text';

    /**
     * Whether a red-flag limit (problem_below / problem_above) can be set.
     */
    public function hasProblemLimits(): bool
    {
        return $this === self::Number || $this === self::Percentage;
    }
}
