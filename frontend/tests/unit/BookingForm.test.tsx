import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { BookingForm } from "@/components/booking/BookingForm";
import { ApiError } from "@/lib/api/client";
import { api } from "@/lib/api/endpoints";
import type { City, Estimate } from "@/lib/api/types";
import { makeRide, renderWithSWR } from "./helpers";

vi.mock("@/lib/api/endpoints", () => ({
  api: { cities: vi.fn(), estimate: vi.fn(), requestRide: vi.fn() },
}));

const mockedApi = vi.mocked(api);

const lahore: City = {
  id: 1,
  name: "Lahore",
  slug: "lahore",
  currency: "PKR",
  center: { lat: 31.52, lng: 74.35 },
  places: [
    { id: 1, name: "Liberty Market", area: "Gulberg", lat: 31.51, lng: 74.34 },
    { id: 2, name: "Packages Mall", area: "Walton", lat: 31.47, lng: 74.36 },
  ],
};

function estimate(category: "economy" | "comfort", driversAvailable: boolean): Estimate {
  return {
    category,
    label: category === "economy" ? "Economy" : "Comfort",
    currency: "PKR",
    total_fare: category === "economy" ? 39000 : 57800,
    distance_meters: 6200,
    duration_seconds: 1000,
    drivers_available: driversAvailable,
    pickup_eta_seconds: driversAvailable ? 240 : null,
  };
}

async function choosePlaces() {
  await userEvent.selectOptions(await screen.findByLabelText("Pickup"), "1");
  await userEvent.selectOptions(screen.getByLabelText("Destination"), "2");
}

describe("BookingForm", () => {
  beforeEach(() => {
    mockedApi.cities.mockResolvedValue([lahore]);
  });

  it("asks for a different destination when both places are the same", async () => {
    renderWithSWR(<BookingForm onBooked={() => {}} onActiveRideFound={() => {}} />);

    await userEvent.selectOptions(await screen.findByLabelText("Pickup"), "1");
    await userEvent.selectOptions(screen.getByLabelText("Destination"), "1");

    expect(screen.getByText("Choose a destination different from the pickup.")).toBeInTheDocument();
    expect(mockedApi.estimate).not.toHaveBeenCalled();
    expect(screen.getByRole("button", { name: "Request ride" })).toBeDisabled();
  });

  it("does not allow a request when no drivers are nearby", async () => {
    mockedApi.estimate.mockResolvedValue([estimate("economy", false), estimate("comfort", false)]);
    renderWithSWR(<BookingForm onBooked={() => {}} onActiveRideFound={() => {}} />);

    await choosePlaces();

    expect(await screen.findAllByText("No drivers nearby right now")).toHaveLength(2);
    expect(screen.getByRole("button", { name: "Request ride" })).toBeDisabled();
  });

  it("requests the ride and hands it back", async () => {
    const ride = makeRide();
    const onBooked = vi.fn();
    mockedApi.estimate.mockResolvedValue([estimate("economy", true), estimate("comfort", true)]);
    mockedApi.requestRide.mockResolvedValue(ride);
    renderWithSWR(<BookingForm onBooked={onBooked} onActiveRideFound={() => {}} />);

    await choosePlaces();
    await screen.findByText("Rs 390");
    await userEvent.click(screen.getByRole("button", { name: "Request ride" }));

    expect(mockedApi.requestRide).toHaveBeenCalledWith("economy", expect.objectContaining({ name: "Liberty Market" }), expect.objectContaining({ name: "Packages Mall" }));
    expect(onBooked).toHaveBeenCalledWith(ride);
  });

  it("switches to the existing ride if the rider already has one", async () => {
    const onActiveRideFound = vi.fn();
    mockedApi.estimate.mockResolvedValue([estimate("economy", true), estimate("comfort", true)]);
    mockedApi.requestRide.mockRejectedValue(new ApiError(409, "active_ride_exists", "You already have a ride in progress."));
    renderWithSWR(<BookingForm onBooked={() => {}} onActiveRideFound={onActiveRideFound} />);

    await choosePlaces();
    await screen.findByText("Rs 390");
    await userEvent.click(screen.getByRole("button", { name: "Request ride" }));

    expect(onActiveRideFound).toHaveBeenCalled();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });
});
