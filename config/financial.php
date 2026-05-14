<?php

return [
    'invoice_types' => [
        'tuition' => 'Tuition',
        'registration' => 'Registration',
        'exam' => 'Exam',
        'custom' => 'Custom',
        'admission' => 'Admission',
        'graduation' => 'Graduation',
        'library_fine' => 'Library Fine',
        'certificate' => 'Certificate',
        'other' => 'Other',
    ],

    'hold_targets' => [
        'registration' => [
            'label' => 'Registration',
            'routes' => ['student.registration.*'],
        ],
        'study_plan' => [
            'label' => 'Study Plan',
            'routes' => ['student.study-plan.*'],
        ],
        'exam_card' => [
            'label' => 'Exam Card',
            'routes' => ['student.exam-card.*'],
        ],
        'transcript' => [
            'label' => 'Transcript',
            'routes' => ['student.transcript.*'],
        ],
        'graduation' => [
            'label' => 'Graduation',
            'routes' => ['student.graduation.*'],
        ],
        'grades' => [
            'label' => 'Grades',
            'routes' => [
                'student.grades.*',
            ],
        ],
        'material' => [
            'label' => 'Course Material',
            'routes' => [
                'student.course-materials.*',
                'student.learning.*',
            ],
        ],
    ],
];
