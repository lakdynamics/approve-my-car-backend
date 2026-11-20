<?php

namespace App\Enums;

/**
 * What picking an answer option means for the inspection.
 */
enum AnswerOutcome: string
{
    /** Passes, e.g. Good, Functioning. */
    case Ok = 'ok';

    /** Shown in red and loses points, e.g. Bad, Not functioning. */
    case Problem = 'problem';

    /** The part isn't fitted, so it's left out of the score, e.g. None. */
    case NotApplicable = 'not_applicable';

    /** Just a fact, not scored, e.g. Steel wheels. */
    case Info = 'info';
}
