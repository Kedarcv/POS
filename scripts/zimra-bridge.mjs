#!/usr/bin/env node

import { chmod, mkdir, readFile, writeFile } from "node:fs/promises";
import process from "node:process";
import {
  FiscalDevice,
  FileStorage,
  registerDevice,
  receiptInputFromJson,
} from "zimra-fdms";

const request = JSON.parse(
  await new Promise((resolve, reject) => {
    let input = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
      input += chunk;
    });
    process.stdin.on("end", () => resolve(input));
    process.stdin.on("error", reject);
  }),
);
const profile = request.profile;

if (typeof profile !== "string" || profile.length === 0) {
  throw new Error("A profile directory is required");
}

await mkdir(profile, { recursive: true, mode: 0o700 });

const deviceFile = `${profile}/device.json`;
const certificateFile = `${profile}/device-certificate.pem`;
const privateKeyFile = `${profile}/device-private-key.pem`;

async function loadDevice() {
  const device = JSON.parse(await readFile(deviceFile, "utf8"));
  const [certificatePem, privateKeyPem] = await Promise.all([
    readFile(certificateFile, "utf8"),
    readFile(privateKeyFile, "utf8"),
  ]);

  return new FiscalDevice(
    device,
    { certificatePem, privateKeyPem },
    {
      environment: device.environment,
      stateDir: profile,
      storage: new FileStorage(profile),
    },
  );
}

async function register() {
  const device = {
    deviceId: Number(request.deviceId),
    serialNumber: String(request.serialNumber),
    modelName: String(request.modelName ?? "Server"),
    modelVersion: String(request.modelVersion ?? "1.0"),
  };
  const environment = request.environment === "production" ? "production" : "test";
  const { keys, certificatePem, operationId } = await registerDevice(
    device,
    String(request.activationKey),
    { environment },
  );

  await Promise.all([
    writeFile(
      deviceFile,
      JSON.stringify({ ...device, environment }, null, 2),
      { mode: 0o600 },
    ),
    writeFile(certificateFile, certificatePem, { mode: 0o600 }),
    writeFile(privateKeyFile, keys.privateKeyPem, { mode: 0o600 }),
  ]);
  await chmod(profile, 0o700);

  return { operationId, device, certificateFile, privateKeyFile };
}

async function run() {
  switch (request.operation) {
    case "register":
      return register();
    case "config":
      return (await loadDevice()).getConfig();
    case "status":
      return (await loadDevice()).getStatus();
    case "reconcile":
      return (await loadDevice()).reconcile();
    case "open":
      return (await loadDevice()).openDay();
    case "close":
      return (await loadDevice()).closeDay();
    case "submit": {
      const device = await loadDevice();
      await device.reconcile();
      await device.getConfig();
      const result = await device.submitReceipt(receiptInputFromJson(request.receipt));
      return {
        ...result,
        qrData: result.qrData ?? null,
        state: device.getState() ?? null,
      };
    }
    default:
      throw new Error(`Unsupported operation: ${String(request.operation)}`);
  }
}

try {
  process.stdout.write(`${JSON.stringify({ success: true, data: await run() })}\n`);
} catch (error) {
  process.stdout.write(
    `${JSON.stringify({
      success: false,
      error: error instanceof Error ? error.message : String(error),
    })}\n`,
  );
  process.exitCode = 1;
}
