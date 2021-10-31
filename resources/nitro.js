exports.handler = async (event, context) => {
  const { handler } = await import('/var/task/index.mjs');
  return handler(event, context);
}
