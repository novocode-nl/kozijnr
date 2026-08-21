"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { zodResolver } from "@hookform/resolvers/zod"
import { useForm } from "react-hook-form"

import { cn } from "@/lib/utils"
import { login } from "@/lib/api"
import { loginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldSeparator,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"

export function LoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  })

  async function onSubmit(values: LoginFormValues) {
    setFormError(null)
    setIsSubmitting(true)

    const result = await login(values)

    setIsSubmitting(false)

    if (!result.success) {
      setFormError(result.message)
      return
    }

    // KOZ-13 rework: no token to store — the backend already set it as an
    // HttpOnly cookie (forwarded by app/api/login/route.ts), so there is
    // nothing left for client-side JS to do with it.
    //
    // KOZ-14 hasn't shipped the real tenant dashboard yet — this ticket's
    // DoD only requires redirecting to "the right follow-up screen" after
    // a successful login, so /dashboard is a deliberate placeholder until
    // KOZ-14 replaces it.
    router.push("/dashboard")
  }

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form
            className="p-6 md:p-8"
            onSubmit={handleSubmit(onSubmit)}
            noValidate
          >
            <FieldGroup>
              <div className="flex flex-col items-center gap-2 text-center">
                <h1 className="text-2xl font-bold">Welkom terug</h1>
                <p className="text-balance text-muted-foreground">
                  Log in op je Kozijnr-account
                </p>
              </div>
              {/*
                KOZ-16: social login bovenaan. Beide knoppen zijn disabled —
                er bestaat nog geen Apple- of Google-login-koppeling in de
                backend, dit is puur de visuele plek voor latere
                implementatie (Google expliciet vereist door het ticket).
              */}
              <Field className="grid grid-cols-2 gap-4">
                <Button variant="outline" type="button" disabled>
                  <AppleIcon />
                  Apple
                </Button>
                <Button variant="outline" type="button" disabled>
                  <GoogleIcon />
                  Google
                </Button>
              </Field>
              <FieldSeparator className="*:data-[slot=field-separator-content]:bg-card">
                Of ga verder met
              </FieldSeparator>
              <Field data-invalid={!!errors.email}>
                <FieldLabel htmlFor="email">E-mailadres</FieldLabel>
                <Input
                  id="email"
                  type="email"
                  placeholder="naam@bedrijf.nl"
                  autoComplete="email"
                  aria-invalid={!!errors.email}
                  {...register("email")}
                />
                <FieldError errors={[errors.email]} />
              </Field>
              <Field data-invalid={!!errors.password}>
                <FieldLabel htmlFor="password">Wachtwoord</FieldLabel>
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={!!errors.password}
                  {...register("password")}
                />
                <FieldError errors={[errors.password]} />
              </Field>
              {formError && (
                <div
                  role="alert"
                  className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                  {formError}
                </div>
              )}
              <Field>
                <Button type="submit" disabled={isSubmitting}>
                  {isSubmitting ? "Bezig met inloggen..." : "Inloggen"}
                </Button>
              </Field>
              <FieldDescription className="text-center">
                <a href="#">Wachtwoord vergeten?</a>
              </FieldDescription>
            </FieldGroup>
          </form>
          <LoginSidePanel />
        </CardContent>
      </Card>
    </div>
  )
}
