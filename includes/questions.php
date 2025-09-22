<?php
declare(strict_types=1);

/**
 * SAFETY_QUESTIONS
 * Add/extend this to match your PDF exactly. Keep the 'code' stable.
 */
const SAFETY_QUESTIONS = [
  // 1.0 Site Set Up
  ['code'=>'1.1', 'text'=>"Is the Site perimeter in place with Temporary works applied for Fixed or Hera's fencing, and are gates secured with lockable devices."],
  ['code'=>'1.2', 'text'=>'Is the site access/egress controlled and safe for pedestrians and vehicles?'],
  ['code'=>'1.3', 'text'=>'Are safe walkways, signage and lighting in place and in good order?'],

  // 2.0 Statutory / First Aid
  ['code'=>'2.1', 'text'=>'Are statutory notices, F10, insurance certs, and first-aid provisions displayed and in date?'],
  ['code'=>'2.2', 'text'=>'Is the accident book available and first-aiders identified?'],

  // 3.0 Site Areas (example)
  ['code'=>'3.3', 'text'=>'Are emergency escape routes clear with fire fighting equipment accessible and in service date?'],

  // Add the rest of your sections/questions here...
];
