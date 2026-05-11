import { DatelessCalendar } from "../booking/calendar";
import BookingForm from "../booking/bookingForm";
import { BookingTypeStepAdmin } from "../../components/booking/bookingTypeSelect";
import { BookingCalendarStepAdmin } from "../../components/booking/bookingCalendar";
import { BookingInfoStepAdmin } from "../../components/booking/bookingInfo";
import { startLoading, stopLoading } from "../../loaders";
import { ErrorResponse, fetchPost, ResponseError } from "../../api/client";
import { errorAlert } from "../../components/booking/bookingStep";

export default function initBookingForm() {
    const form = document.getElementById("booking-form") as HTMLFormElement;
    if (!form) {
        console.error("Form is required");
        return;
    }

    const formObj = new BookingForm(
        form,
        new BookingTypeStepAdmin(form.querySelector<HTMLDivElement>("#booking-form-type")),
        new BookingCalendarStepAdmin(
            form.querySelector<HTMLDivElement>("#booking-form-times"),
            new DatelessCalendar(form.querySelector<HTMLDivElement>("#booking-form-times .calendar"))
        ),
        new BookingInfoStepAdmin(form.querySelector<HTMLDivElement>("#booking-form-details")),
        (data, obj)=>  {
            startLoading();

            fetchPost(form.action, data)
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
}