<?php

namespace Vipa\JournalBundle\Validator\Constraints;

use Vipa\JournalBundle\Validator\JournalIssnValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class JournalIssn extends Constraint
{
    public $message = 'There is an journal uses this issn or eissn already';

    public function validatedBy()
    {
        return JournalIssnValidator::class;
    }
}
