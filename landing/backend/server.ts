const kv = await Deno.openKv();

// Initialize if not exists
if (!(await kv.get(["lastVisit"])).value) {
  await kv.set(["lastVisit"], { city: "Unknown", country: "Unknown", time: Date.now() });
}

const clients = new Map<string, ReadableStreamDefaultController>();

// Watch for changes to the "lastVisit" key
const watcher = kv.watch([["lastVisit"]]);
(async () => {
  for await (const entries of watcher) {
    const lastVisit = entries[0].value;
    const message = `data: ${JSON.stringify(lastVisit)}\n\n`;
    const encoder = new TextEncoder();
    
    for (const [id, controller] of clients.entries()) {
      try {
        controller.enqueue(encoder.encode(message));
      } catch (err) {
        clients.delete(id);
      }
    }
  }
})();

console.log("Server listening on http://localhost:8000");

// A basic cache to avoid registering the exact same IP repeatedly in a short time
// But locally we can just register it.

Deno.serve({ port: 8000 }, async (req: Request) => {
  const url = new URL(req.url);

  // Handle CORS
  const headers = new Headers({
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
  });

  if (req.method === "OPTIONS") {
    return new Response(null, { headers });
  }

  // SSE Endpoint for visits
  if (req.method === "GET" && url.pathname === "/api/visit") {
    
    // Attempt to parse location from Deno Deploy headers
    // Fallbacks for local testing
    let city = req.headers.get("x-city");
    let country = req.headers.get("x-country");
    
    // Use an external API for local testing if running locally, or just mock it better
    if (!city) {
        city = "Medellín";
        country = "Colombia";
    }

    const time = Date.now();

    // Setup the SSE connection
    let controllerId: string;
    const stream = new ReadableStream({
      start(controller) {
        controllerId = crypto.randomUUID();
        clients.set(controllerId, controller);
      },
      cancel() {
        clients.delete(controllerId);
      }
    });

    // Update the database asynchronously so we don't block the stream response
    // The kv.watch will automatically notify this client and all others!
    setTimeout(async () => {
        await kv.set(["lastVisit"], { city, country, time });
    }, 100);

    return new Response(stream, {
      headers: {
        "Content-Type": "text/event-stream",
        "Cache-Control": "no-cache",
        "Connection": "keep-alive",
        ...Object.fromEntries(headers.entries())
      }
    });
  }

  return new Response("Not found", { status: 404, headers });
});
