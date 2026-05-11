import BookingForm from "./bookingForm";
import initCalendar, { compositeCalendars } from "./calendar";
import { BookingTypeStepPublic } from "../../components/booking/bookingTypeSelect";
import { BookingCalendarStepPublic } from "../../components/booking/bookingCalendar";
import { BookingInfoStepPublic } from "../../components/booking/bookingInfo";
import { startLoading, stopLoading } from "../../loaders";
import { saveBooking } from "../../api/endpoints/booking";
import { ErrorResponse, ResponseError } from "../../api/client";
import { errorAlert } from "../../components/booking/bookingStep";

declare global {
    const playersRangeLabel: string;
    const errorAlertMsg: string;
    const missingPlayerCount: string;
    const requiredTime: string;
    const requiredField : string;
    const emailError : string;
    const phoneError : string;
    const numberError : string;
    const numberMinError : string;
    const numberMaxError : string;
}

export default function initBookingPage() : void {
    const form = document.getElementById('booking-form') as HTMLFormElement;
    const bookingForm = new BookingForm(
        form,
        new BookingTypeStepPublic(form.querySelector<HTMLDivElement>('#types')),
        new BookingCalendarStepPublic(form.querySelector<HTMLDivElement>('#dateTime'), compositeCalendars),
        new BookingInfoStepPublic(form.querySelector<HTMLDivElement>('#personalInfo')),
        (data, obj)=>  {
            startLoading();

            // Send booking data
            saveBooking(obj.arenaSlug, data)
                .then((response) => {
                    stopLoading(true);
                    if (response.values.redirect) {
                        window.location.href = response.values.redirect;
                    }
                })
                .catch(async (error: ResponseError) => {
                    const data = await error.data as ErrorResponse<{ [key: string]: string | string[] }>;
                    const errors = data.values;
                    console.log(error, data);
                    stopLoading(false);

                    // Display errors
                    if (data.values) {
                        // Global error message = "Formulář obsahuje chyby"
                        obj.errorsWrapper.appendChild(errorAlert(errorAlertMsg));

                        Object.entries(errors).forEach(([key, error]) => {
                            if ((typeof error) === "string") {
                                obj.addError(key, error);
                            } else if (error instanceof Array) {
                                error.forEach(error1 => {
                                    obj.addError(key, error1);
                                });
                            }
                        });
                    }

                    // Scroll the first step with an error into view
                    let found = false;
                    obj.steps.forEach((step, name) => {
                        if (found || !step.hasError) return;
                        found = true;
                        obj.scrollToStep(name);
                    });
                });
        }
    );
    initCalendar();
}