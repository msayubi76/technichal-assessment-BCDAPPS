import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RideTracker } from "@/components/ride/RideTracker";
import { ApiError } from "@/lib/api/client";
import { api } from "@/lib/api/endpoints";
import { makeRide, renderWithSWR } from "./helpers";

vi.mock("@/lib/api/endpoints", () => ({
  api: { ride: vi.fn(), cancelRide: vi.fn(), riderComing: vi.fn() },
}));

// No WebSocket in tests: the screen should still work through polling.
vi.mock("@/lib/realtime", () => ({
  listenToRide: () => () => {},
  isRealtimeConnected: () => false,
  subscribeToConnection: () => () => {},
}));

const rideApi = vi.mocked(api);

describe("RideTracker", () => {
  beforeEach(() => {
    rideApi.ride.mockReset();
    rideApi.cancelRide.mockReset();
  });

  it("shows a loading placeholder, then the ride", async () => {
    rideApi.ride.mockResolvedValue(makeRide());

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    expect(screen.getByRole("status", { name: "Loading" })).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "Finding you a driver" })).toBeInTheDocument();
  });

  it("shows the error with its reference and loads again on retry", async () => {
    rideApi.ride
      .mockRejectedValueOnce(new ApiError(0, "network_error", "Can't reach the server.", {}, "req-12345678"))
      .mockResolvedValueOnce(makeRide());

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    expect(await screen.findByRole("alert")).toHaveTextContent("Can't reach the server.");
    expect(screen.getByRole("alert")).toHaveTextContent("Reference: req-12345678");

    await userEvent.click(screen.getByRole("button", { name: "Try again" }));

    expect(await screen.findByRole("heading", { name: "Finding you a driver" })).toBeInTheDocument();
  });

  it("asks before cancelling, then shows the cancelled ride", async () => {
    rideApi.ride.mockResolvedValue(makeRide());
    rideApi.cancelRide.mockResolvedValue(
      makeRide({ status: "cancelled", cancelled_by: "rider", can_cancel: false }),
    );

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    await userEvent.click(await screen.findByRole("button", { name: "Cancel ride" }));
    expect(rideApi.cancelRide).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole("button", { name: "Yes, cancel" }));

    expect(await screen.findByRole("heading", { name: "Ride cancelled" })).toBeInTheDocument();
    expect(rideApi.cancelRide).toHaveBeenCalledWith(makeRide().id);
    expect(screen.queryByRole("button", { name: "Cancel ride" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Book another ride" })).toBeInTheDocument();
  });

  it("lets the rider tell a waiting driver they're coming", async () => {
    const arrived = makeRide({ status: "arrived", arrived_at: "2026-10-30T10:05:00Z" });
    rideApi.ride.mockResolvedValue(arrived);
    rideApi.riderComing.mockResolvedValue({ ...arrived, rider_coming_at: "2026-10-30T10:06:00Z" });

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    expect(await screen.findByRole("heading", { name: "Your driver has arrived" })).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "I'm coming" }));

    expect(await screen.findByText("Your driver knows you are coming.")).toBeInTheDocument();
    expect(rideApi.riderComing).toHaveBeenCalledWith(arrived.id);
    expect(screen.queryByRole("button", { name: "I'm coming" })).not.toBeInTheDocument();
  });

  it("does not offer \"I'm coming\" before the driver arrives", async () => {
    rideApi.ride.mockResolvedValue(makeRide({ status: "accepted" }));

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    expect(await screen.findByRole("heading", { name: "Your driver is on the way" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "I'm coming" })).not.toBeInTheDocument();
  });

  it("hides the cancel button when the ride can no longer be cancelled", async () => {
    rideApi.ride.mockResolvedValue(makeRide({ status: "completed", can_cancel: false }));

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    expect(await screen.findByRole("heading", { name: "You have arrived" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Cancel ride" })).not.toBeInTheDocument();
  });

  it("shows why a cancel failed and reloads the ride", async () => {
    rideApi.ride
      .mockResolvedValueOnce(makeRide())
      .mockResolvedValue(makeRide({ status: "completed", can_cancel: false }));
    rideApi.cancelRide.mockRejectedValue(new ApiError(409, "invalid_transition", "This ride is already completed."));

    renderWithSWR(<RideTracker rideId="r1" onBookAnother={() => {}} />);

    await userEvent.click(await screen.findByRole("button", { name: "Cancel ride" }));
    await userEvent.click(screen.getByRole("button", { name: "Yes, cancel" }));

    await waitFor(() => expect(screen.getByRole("heading", { name: "You have arrived" })).toBeInTheDocument());
    expect(rideApi.ride).toHaveBeenCalledTimes(2);
  });
});
