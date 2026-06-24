<?php

function lecturer_assessment_rubric($isAccountingIS)
{
    $logInformation = [
        'Poor' => 'The information is poorly organised.',
        'Fair' => 'The information is somewhat organised.',
        'Good' => 'The information is well organised.',
        'Excellent' => 'The information is very well organised.',
    ];
    $logComplete = [
        'Poor' => 'Several entries are missing.',
        'Fair' => 'Three or more entries are missing.',
        'Good' => 'Only one or two entries are missing.',
        'Excellent' => 'All entries are covered.',
    ];
    $logSupport = [
        'Poor' => 'Entries do not include supporting details, examples, or employer verification.',
        'Fair' => 'Three or more entries lack supporting details, examples, or employer verification.',
        'Good' => 'Only one or two entries lack supporting details, examples, or employer verification.',
        'Excellent' => 'All entries include supporting details, examples, and employer verification.',
    ];
    $reflection = [
        'Poor' => 'Expresses very limited or no connection between the task and self.',
        'Fair' => 'Expresses some connection between the task and self.',
        'Good' => 'Explains how the student could change as a result of the task.',
        'Excellent' => 'Expresses actual changes in self because of the task.',
    ];
    $coherence = [
        'Poor' => 'The flow of writing as a whole lacks coherence or is unclear.',
        'Fair' => 'The writing is coherent, but paragraphs are not well structured.',
        'Good' => 'The writing is coherent with well-constructed paragraphs.',
        'Excellent' => 'The writing is coherent with well-constructed paragraphs and subheadings.',
    ];
    $information = [
        'Poor' => 'One or more topics are not addressed, and information has little or nothing to do with the main topic.',
        'Fair' => 'Topics are addressed briefly; information relates to the topic but lacks details or examples.',
        'Good' => 'Topics are addressed with relevant information and one or two supporting details or examples.',
        'Excellent' => 'All topics and questions are addressed with several relevant supporting details or examples.',
    ];
    $analysis = [
        'Poor' => 'Provides insufficient evaluation and critical analysis of the topic.',
        'Fair' => 'Provides somewhat adequate evaluation and critical analysis of the topic.',
        'Good' => 'Demonstrates thoughtful evaluation and critical analysis of the topic.',
        'Excellent' => 'Demonstrates sophisticated evaluation and critical analysis of the topic.',
    ];
    $grammar = [
        'Poor' => 'Contains many grammatical, spelling, or punctuation errors.',
        'Fair' => 'Contains some grammatical, spelling, or punctuation errors.',
        'Good' => 'Contains only a few grammatical, spelling, or punctuation errors.',
        'Excellent' => 'Contains no grammatical, spelling, or punctuation errors.',
    ];
    $appearance = [
        'Poor' => 'Appearance is unacceptable, with inappropriate formatting and unclear style.',
        'Fair' => 'Appearance is acceptable, but many elements could be improved.',
        'Good' => 'Appearance is generally good; only some elements need improvement.',
        'Excellent' => 'Formatting and appearance are excellent and appropriately use tables, figures, fonts, spacing, and borders.',
    ];
    $references = [
        'Poor' => 'Sources are inaccurately documented, with few or no in-text citations or references and incorrect style.',
        'Fair' => 'Sources are documented, but many citations and references use an incorrect style or format.',
        'Good' => 'Sources are accurately documented with mostly correct citations, reference entries, style, and format.',
        'Excellent' => 'All sources are accurately documented with complete citations and references in the correct style and format.',
    ];

    $rubric = [
        'log_organization' => $logInformation,
        'log_complete' => $logComplete,
        'log_support' => $logSupport,
        'log_reflection' => $reflection,
        'report_introduction' => $coherence,
        'report_methodology' => $information,
        'report_analysis' => $analysis,
        'report_conclusion' => $coherence,
        'report_organization' => $coherence,
        'report_mechanism' => $grammar,
        'report_aesthetics' => $appearance,
        'report_timeliness' => $information,
        'report_overall' => $references,
    ];

    if (!$isAccountingIS) {
        return $rubric;
    }

    $dataStructure = [
        'Poor' => 'Shows limited ability to identify required tables, fields, properties, and relationships; most elements are omitted.',
        'Fair' => 'Shows fair ability to identify tables, fields, properties, and relationships, but many elements are omitted.',
        'Good' => 'Shows good ability to identify tables, fields, properties, and relationships, but some elements are omitted.',
        'Excellent' => 'Shows excellent ability to identify all required tables, fields, properties, and relationships.',
    ];
    $systemControl = [
        'Poor' => 'Applies limited information-system security and control measures.',
        'Fair' => 'Applies fair security and control measures, but substantial improvement is needed.',
        'Good' => 'Applies good security and control measures, with only some improvement needed.',
        'Excellent' => 'Applies excellent information-system security and control measures.',
    ];
    $interface = [
        'Poor' => 'Demonstrates poor system-interface design.', 'Fair' => 'Demonstrates fair system-interface design.',
        'Good' => 'Demonstrates good system-interface design.', 'Excellent' => 'Demonstrates excellent system-interface design.',
    ];
    $maintenance = [
        'Poor' => 'The system lacks basic data insertion, update, and deletion capabilities.',
        'Fair' => 'The system demonstrates fair data insertion, update, and deletion capabilities.',
        'Good' => 'The system demonstrates good data insertion, update, and deletion capabilities.',
        'Excellent' => 'The system demonstrates excellent data insertion, update, and deletion capabilities.',
    ];
    $output = [
        'Poor' => 'The system lacks adequate output for decision making.',
        'Fair' => 'The system demonstrates adequate output for decision making.',
        'Good' => 'The system demonstrates good output for decision making.',
        'Excellent' => 'The system provides varied outputs, including complex analysis for decision making.',
    ];
    $problemSolving = [
        'Poor' => 'The system has very limited originality and contribution to its intended purpose.',
        'Fair' => 'The system includes some new elements and makes a fair contribution to its intended purpose.',
        'Good' => 'The system is new, helpful, interesting, and makes a considerable contribution.',
        'Excellent' => 'The system is unique, helpful, very interesting, and makes a great contribution.',
    ];
    $organization = [
        'Poor' => 'The presenter does not follow a logical sequence and provides no elaboration.',
        'Fair' => 'The presenter follows a logical sequence but fails to elaborate.',
        'Good' => 'The presentation has a logical sequence with good explanations and elaboration.',
        'Excellent' => 'The presenter follows a logical sequence with excellent explanations and elaboration.',
    ];
    $delivery = [
        'Poor' => 'Delivers only some ideas and requires substantial improvement.',
        'Fair' => 'Delivers ideas fairly clearly and requires minor improvement.',
        'Good' => 'Delivers ideas clearly.', 'Excellent' => 'Delivers ideas with great clarity and creativity.',
    ];
    $visual = [
        'Poor' => 'Uses inappropriate multimedia support and visual aids.',
        'Fair' => 'Uses average-quality multimedia support and visual aids.',
        'Good' => 'Uses good-quality multimedia support and visual aids.',
        'Excellent' => 'Uses excellent-quality multimedia support and visual aids.',
    ];
    $nonVerbal = [
        'Poor' => 'Makes minimal eye contact with inappropriate gestures, posture, and appearance.',
        'Fair' => 'Makes some eye contact with acceptable gestures, posture, and appearance.',
        'Good' => 'Usually maintains eye contact with good gestures, posture, and appearance.',
        'Excellent' => 'Maintains excellent eye contact with commendable gestures, posture, and appearance.',
    ];
    $verbal = [
        'Poor' => 'Has minimal audience interaction, major pronunciation or grammar errors, and an inaudible voice.',
        'Fair' => 'Has some audience interaction and some pronunciation or grammar errors, but can be heard.',
        'Good' => 'Interacts well, pronounces terms correctly, has minimal grammar errors, and speaks clearly.',
        'Excellent' => 'Interacts excellently, pronounces terms perfectly, has no grammar errors, and speaks clearly.',
    ];

    return array_merge($rubric, [
        'system_analyze' => $dataStructure,
        'system_security' => $systemControl,
        'system_interface' => $interface,
        'system_reports' => $output,
        'system_queries' => $maintenance,
        'system_practicality' => $problemSolving,
        'system_ease_use' => $interface,
        'system_enhanced' => $problemSolving,
        'system_creativity' => $problemSolving,
        'present_organization' => $organization,
        'present_subject' => $delivery,
        'present_visual' => $visual,
        'present_non_verbal' => $nonVerbal,
        'present_enthusiasm' => $delivery,
        'present_elocution' => $verbal,
    ]);
}

function lecturer_score_hint($score, array $criterionRubric)
{
    if ($score <= 3) {
        $band = 'Poor';
    } elseif ($score <= 6) {
        $band = 'Fair';
    } elseif ($score <= 9) {
        $band = 'Good';
    } else {
        $band = 'Excellent';
    }

    return $band . ' (' . ($band === 'Poor' ? '0-3' : ($band === 'Fair' ? '4-6' : ($band === 'Good' ? '7-9' : '10-12'))) . '): ' . ($criterionRubric[$band] ?? '');
}
