import { execSync } from "node:child_process";
import path from "node:path";

/**
 * Drivers move to the destination after every trip, so earlier runs leave
 * them far from the pickup this test uses. Put the idle demo drivers back
 * where they started before the test begins.
 */
export default function globalSetup() {
  execSync("docker compose exec -T api php artisan db:seed --class=ResetDemoDriversSeeder --force", {
    cwd: path.resolve(__dirname, "../../.."),
    stdio: "inherit",
  });
}
