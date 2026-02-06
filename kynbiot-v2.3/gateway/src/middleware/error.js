export function notFoundHandler(req, res) {
  return res.status(404).json({ error: `Not found: ${req.method} ${req.path}` });
}

export function errorHandler(err, req, res, _next) {
  const status = err.status || 500;
  if (status >= 500) {
    console.error('[gateway] unhandled error', err);
  }
  return res.status(status).json({ error: err.message || 'Internal Server Error' });
}
