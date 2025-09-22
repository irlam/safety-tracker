<?php
// Pretty error output for development; safe to keep (it only changes formatting)
set_error_handler(function($severity, $message, $file, $line) {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e){
  http_response_code(500);
  $msg = htmlspecialchars($e->getMessage());
  $file= htmlspecialchars($e->getFile());
  $line= (int)$e->getLine();
  echo "<pre style='background:#1f2937;color:#e5e7eb;padding:16px;border-radius:12px;font:14px/1.5 ui-monospace,Consolas'>";
  echo "Fatal error:\n$msg\n\n$file:$line\n\n";
  echo htmlspecialchars($e->getTraceAsString());
  echo "</pre>";
});
