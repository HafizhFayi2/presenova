const fs = require("fs");
const path = require("path");
const webpush = require("web-push");

function readJson(filePath) {
  const raw = fs.readFileSync(filePath, "utf8");
  return JSON.parse(raw);
}

function normalizePayload(payload) {
  if (payload == null) {
    return "";
  }
  if (typeof payload === "string") {
    return payload;
  }
  return JSON.stringify(payload);
}

async function sendAll(tasks, vapidDetails) {
  webpush.setVapidDetails(
    vapidDetails.subject || "mailto:admin@localhost",
    vapidDetails.publicKey,
    vapidDetails.privateKey
  );

  const results = [];

  for (const task of tasks) {
    const payload = normalizePayload(task.payload);
    try {
      await webpush.sendNotification(task.subscription, payload, task.options || {});
      results.push({
        id: task.id,
        success: true
      });
    } catch (err) {
      results.push({
        id: task.id,
        success: false,
        statusCode: err.statusCode || 0,
        message: err.body || err.message || "Unknown error"
      });
    }
  }

  return results;
}

async function main() {
  const inputPath = process.argv[2];
  if (!inputPath) {
    console.error("Missing input file path");
    process.exit(1);
  }

  const resolved = path.resolve(inputPath);
  if (!fs.existsSync(resolved)) {
    console.error("Input file not found:", resolved);
    process.exit(1);
  }

  const data = readJson(resolved);
  if (!data || !data.vapid || !data.tasks) {
    console.error("Invalid payload format");
    process.exit(1);
  }

  const vapid = data.vapid;
  if (!vapid.publicKey || !vapid.privateKey) {
    console.error("Missing VAPID keys");
    process.exit(1);
  }

  const tasks = Array.isArray(data.tasks) ? data.tasks : [];
  const results = await sendAll(tasks, vapid);
  process.stdout.write(JSON.stringify({ results }));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
